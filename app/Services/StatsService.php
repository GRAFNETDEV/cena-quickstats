<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class StatsService
{
    private function applyScope($q, array $scope)
    {
        foreach ($scope as $k => $v) {
            if ($v === null || $v === '') continue;
            $q->where($k, $v);
        }
        return $q;
    }

    private function validPvStatuses(): array
    {
        return ['valide', 'publie'];
    }

    private function dominantNiveau(int $electionId): ?string
    {
        $row = DB::table('proces_verbaux')
            ->selectRaw('niveau, COUNT(*) as c')
            ->where('election_id', $electionId)
            ->whereIn('statut', $this->validPvStatuses())
            ->groupBy('niveau')
            ->orderByDesc('c')
            ->first();

        return $row?->niveau;
    }

    private function inscritsCena(array $scope = []): int
    {
        $q = DB::table('postes_vote as pv')
            ->join('villages_quartiers as vq', 'vq.id', '=', 'pv.village_quartier_id')
            ->join('arrondissements as ar', 'ar.id', '=', 'vq.arrondissement_id')
            ->join('communes as co', 'co.id', '=', 'ar.commune_id')
            ->join('departements as de', 'de.id', '=', 'co.departement_id')
            // ✅ circonscription au niveau arrondissement
            ->leftJoin('circonscriptions_electorales as ci', 'ci.id', '=', 'ar.circonscription_id')
            ->selectRaw('COALESCE(SUM(pv.electeurs_inscrits), 0) as total');

        $this->applyScope($q, $scope);
        return (int) $q->value('total');
    }

    /**
     * KPI au niveau PV arrondissement (sans doublons : dernier PV par arrondissement via created_at)
     * - votants + suffrages exprimés: depuis proces_verbaux
     * - bulletins nuls: somme pv_lignes.bulletins_nuls pour les PV retenus
     */
    private function computeKpisArrondissement(int $electionId, array $scope = []): array
    {
        $inscritsCena = $this->inscritsCena($scope);
        $validStatuses = $this->validPvStatuses();

        $base = DB::table('proces_verbaux as pr')
            ->join('arrondissements as ar', 'ar.id', '=', 'pr.niveau_id')
            ->join('communes as co', 'co.id', '=', 'ar.commune_id')
            ->join('departements as de', 'de.id', '=', 'co.departement_id')
            // ✅ circonscription au niveau arrondissement
            ->leftJoin('circonscriptions_electorales as ci', 'ci.id', '=', 'ar.circonscription_id')
            ->where('pr.election_id', $electionId)
            ->where('pr.niveau', 'arrondissement')
            ->whereIn('pr.statut', $validStatuses);

        $allowed = array_intersect_key($scope, array_flip(['de.id', 'co.id', 'ci.id', 'ar.id']));
        $this->applyScope($base, $allowed);

        // ✅ Dedup: dernier PV par arrondissement via created_at
        $dedup = DB::query()->fromSub(
            $base->selectRaw(
                "pr.id as pv_id,
                 pr.niveau_id as arrondissement_id,
                 COALESCE(pr.nombre_votants, 0) as nombre_votants,
                 COALESCE(pr.nombre_suffrages_exprimes, 0) as nombre_suffrages_exprimes,
                 pr.created_at,
                 ROW_NUMBER() OVER (
                     PARTITION BY pr.niveau_id
                     ORDER BY pr.created_at DESC NULLS LAST, pr.id DESC
                 ) as rn"
            ),
            'base'
        )->where('rn', 1);

        // ✅ inscrits comptabilisés: postes des villages de ces arrondissements (référence CENA)
        $inscritsComp = (int) DB::query()->fromSub($dedup, 'd')
            ->join('villages_quartiers as vq', 'vq.arrondissement_id', '=', 'd.arrondissement_id')
            ->join('postes_vote as pv', 'pv.village_quartier_id', '=', 'vq.id')
            ->selectRaw('COALESCE(SUM(pv.electeurs_inscrits), 0) as total')
            ->value('total');

        // ✅ bulletins nuls: somme des pv_lignes du PV retenu (pas dans proces_verbaux)
        $bnSub = DB::table('pv_lignes as l')
            ->selectRaw('l.proces_verbal_id, COALESCE(SUM(COALESCE(l.bulletins_nuls,0)),0) as nuls_pv')
            ->groupBy('l.proces_verbal_id');

        $agg = DB::query()->fromSub($dedup, 'd')
            ->leftJoinSub($bnSub, 'bn', 'bn.proces_verbal_id', '=', 'd.pv_id')
            ->selectRaw('COUNT(*) as nb_pv')
            ->selectRaw('COALESCE(SUM(d.nombre_votants), 0) as votants')
            ->selectRaw('COALESCE(SUM(d.nombre_suffrages_exprimes), 0) as suffrages')
            ->selectRaw('COALESCE(SUM(COALESCE(bn.nuls_pv,0)), 0) as nuls')
            ->first();

        $votants = (int)($agg->votants ?? 0);

        $couverture = $inscritsCena > 0 ? round(($inscritsComp / $inscritsCena) * 100, 2) : 0.0;
        $participationGlobal = $inscritsCena > 0 ? round(($votants / $inscritsCena) * 100, 2) : 0.0;
        $participationBureaux = $inscritsComp > 0 ? round(($votants / $inscritsComp) * 100, 2) : 0.0;

        return [
            'nombre_pv_valides' => (int)($agg->nb_pv ?? 0),
            'inscrits_cena' => $inscritsCena,
            'inscrits_comptabilises' => $inscritsComp,
            'couverture_saisie' => $couverture,
            'nombre_votants' => $votants,
            'nombre_suffrages_exprimes' => (int)($agg->suffrages ?? 0),
            'nombre_bulletins_nuls' => (int)($agg->nuls ?? 0),
            'taux_participation_global' => $participationGlobal,
            'taux_participation_bureaux_comptabilises' => $participationBureaux,
        ];
    }

    /**
     * KPI au niveau PV village_quartier (sans doublons : dernier PV par village_quartier via created_at)
     * - votants + suffrages: proces_verbaux
     * - bulletins nuls: somme pv_lignes du PV (si PV a des lignes)
     */
    private function computeKpisVillageQuartier(int $electionId, array $scope = []): array
    {
        $inscritsCena = $this->inscritsCena($scope);
        $validStatuses = $this->validPvStatuses();

        $base = DB::table('proces_verbaux as pr')
            ->join('villages_quartiers as vq', 'vq.id', '=', 'pr.niveau_id')
            ->join('arrondissements as ar', 'ar.id', '=', 'vq.arrondissement_id')
            ->join('communes as co', 'co.id', '=', 'ar.commune_id')
            ->join('departements as de', 'de.id', '=', 'co.departement_id')
            // ✅ circonscription au niveau arrondissement
            ->leftJoin('circonscriptions_electorales as ci', 'ci.id', '=', 'ar.circonscription_id')
            ->where('pr.election_id', $electionId)
            ->where('pr.niveau', 'village_quartier')
            ->whereIn('pr.statut', $validStatuses);

        $this->applyScope($base, $scope);

        // ✅ Dedup: dernier PV par village via created_at
        $dedup = DB::query()->fromSub(
            $base->selectRaw(
                "pr.id as pv_id,
                 pr.niveau_id as village_quartier_id,
                 COALESCE(pr.nombre_votants, 0) as nombre_votants,
                 COALESCE(pr.nombre_suffrages_exprimes, 0) as nombre_suffrages_exprimes,
                 pr.created_at,
                 ROW_NUMBER() OVER (
                     PARTITION BY pr.niveau_id
                     ORDER BY pr.created_at DESC NULLS LAST, pr.id DESC
                 ) as rn"
            ),
            'base'
        )->where('rn', 1);

        $inscritsComp = (int) DB::query()->fromSub($dedup, 'd')
            ->join('postes_vote as pv', 'pv.village_quartier_id', '=', 'd.village_quartier_id')
            ->selectRaw('COALESCE(SUM(pv.electeurs_inscrits), 0) as total')
            ->value('total');

        $bnSub = DB::table('pv_lignes as l')
            ->selectRaw('l.proces_verbal_id, COALESCE(SUM(COALESCE(l.bulletins_nuls,0)),0) as nuls_pv')
            ->groupBy('l.proces_verbal_id');

        $agg = DB::query()->fromSub($dedup, 'd')
            ->leftJoinSub($bnSub, 'bn', 'bn.proces_verbal_id', '=', 'd.pv_id')
            ->selectRaw('COUNT(*) as nb_pv')
            ->selectRaw('COALESCE(SUM(d.nombre_votants), 0) as votants')
            ->selectRaw('COALESCE(SUM(d.nombre_suffrages_exprimes), 0) as suffrages')
            ->selectRaw('COALESCE(SUM(COALESCE(bn.nuls_pv,0)), 0) as nuls')
            ->first();

        $votants = (int)($agg->votants ?? 0);

        $couverture = $inscritsCena > 0 ? round(($inscritsComp / $inscritsCena) * 100, 2) : 0.0;
        $participationGlobal = $inscritsCena > 0 ? round(($votants / $inscritsCena) * 100, 2) : 0.0;
        $participationBureaux = $inscritsComp > 0 ? round(($votants / $inscritsComp) * 100, 2) : 0.0;

        return [
            'nombre_pv_valides' => (int)($agg->nb_pv ?? 0),
            'inscrits_cena' => $inscritsCena,
            'inscrits_comptabilises' => $inscritsComp,
            'couverture_saisie' => $couverture,
            'nombre_votants' => $votants,
            'nombre_suffrages_exprimes' => (int)($agg->suffrages ?? 0),
            'nombre_bulletins_nuls' => (int)($agg->nuls ?? 0),
            'taux_participation_global' => $participationGlobal,
            'taux_participation_bureaux_comptabilises' => $participationBureaux,
        ];
    }

    /**
     * KPI calculés depuis pv_lignes (utile pour les niveaux où les PV ne portent pas les agrégats propres)
     * ✅ Sans doublons :
     * - Dernier PV par arrondissement (created_at)
     * - Puis dernière ligne par (arrondissement, village) (created_at)
     * - Suffrages = somme(total_voix)
     * - Nuls = somme(bulletins_nuls)
     * - Votants = suffrages + nuls
     */
    private function computeKpisFromLignes(int $electionId, array $scope = []): array
    {
        $inscritsCena = $this->inscritsCena($scope);
        $validStatuses = $this->validPvStatuses();

        $voixSub = DB::table('pv_ligne_resultats as r')
            ->selectRaw('r.pv_ligne_id, COALESCE(SUM(r.nombre_voix), 0) as total_voix')
            ->groupBy('r.pv_ligne_id');

        // ✅ Dernier PV par arrondissement (created_at)
        $pvDernier = DB::table('proces_verbaux as p')
            ->join('arrondissements as ar', 'ar.id', '=', 'p.niveau_id')
            ->join('communes as co', 'co.id', '=', 'ar.commune_id')
            ->join('departements as de', 'de.id', '=', 'co.departement_id')
            ->leftJoin('circonscriptions_electorales as ci', 'ci.id', '=', 'ar.circonscription_id')
            ->where('p.election_id', $electionId)
            ->where('p.niveau', 'arrondissement')
            ->whereIn('p.statut', $validStatuses);

        $this->applyScope($pvDernier, $scope);

        $dernierPvArr = DB::query()->fromSub(
            $pvDernier->selectRaw(
                "p.id as proces_verbal_id,
                 p.niveau_id as pv_arrondissement_id,
                 p.created_at,
                 ROW_NUMBER() OVER (
                     PARTITION BY p.niveau_id
                     ORDER BY p.created_at DESC NULLS LAST, p.id DESC
                 ) as rn"
            ),
            'pva'
        )->where('rn', 1);

        // ✅ Lignes sur ces PV + dédup par (arrondissement, village) via created_at
        $base = DB::query()->fromSub($dernierPvArr, 'd')
            ->join('pv_lignes as l', 'l.proces_verbal_id', '=', 'd.proces_verbal_id')
            ->join('villages_quartiers as vq', 'vq.id', '=', 'l.village_quartier_id')
            ->join('arrondissements as ar', 'ar.id', '=', 'vq.arrondissement_id')
            ->join('communes as co', 'co.id', '=', 'ar.commune_id')
            ->join('departements as de', 'de.id', '=', 'co.departement_id')
            ->leftJoin('circonscriptions_electorales as ci', 'ci.id', '=', 'ar.circonscription_id')
            ->leftJoinSub($voixSub, 'voix', 'voix.pv_ligne_id', '=', 'l.id')
            ->whereNotNull('l.village_quartier_id');

        $this->applyScope($base, $scope);

        $dedup = DB::query()->fromSub(
            $base->selectRaw(
                "l.id,
                 l.village_quartier_id,
                 l.proces_verbal_id,
                 d.pv_arrondissement_id,
                 COALESCE(l.bulletins_nuls, 0) as bulletins_nuls,
                 COALESCE(voix.total_voix, 0) as total_voix,
                 l.created_at,
                 ROW_NUMBER() OVER (
                     PARTITION BY d.pv_arrondissement_id, l.village_quartier_id
                     ORDER BY l.created_at DESC NULLS LAST, l.id DESC
                 ) as rn"
            ),
            'base'
        )->where('rn', 1);

        $inscritsComp = (int) DB::query()->fromSub($dedup, 'ls')
            ->join('postes_vote as pv', 'pv.village_quartier_id', '=', 'ls.village_quartier_id')
            ->selectRaw('COALESCE(SUM(pv.electeurs_inscrits), 0) as total')
            ->value('total');

        $agg = DB::query()->fromSub($dedup, 'ls')
            ->selectRaw('COUNT(DISTINCT ls.proces_verbal_id) as nb_pv')
            ->selectRaw('COALESCE(SUM(ls.total_voix), 0) as suffrages')
            ->selectRaw('COALESCE(SUM(ls.bulletins_nuls), 0) as nuls')
            ->selectRaw('COALESCE(SUM(ls.total_voix + ls.bulletins_nuls), 0) as votants')
            ->first();

        $votants = (int)($agg->votants ?? 0);

        $couverture = $inscritsCena > 0 ? round(($inscritsComp / $inscritsCena) * 100, 2) : 0.0;
        $participationGlobal = $inscritsCena > 0 ? round(($votants / $inscritsCena) * 100, 2) : 0.0;
        $participationBureaux = $inscritsComp > 0 ? round(($votants / $inscritsComp) * 100, 2) : 0.0;

        return [
            'nombre_pv_valides' => (int)($agg->nb_pv ?? 0),
            'inscrits_cena' => $inscritsCena,
            'inscrits_comptabilises' => $inscritsComp,
            'couverture_saisie' => $couverture,
            'nombre_votants' => $votants,
            'nombre_suffrages_exprimes' => (int)($agg->suffrages ?? 0),
            'nombre_bulletins_nuls' => (int)($agg->nuls ?? 0),
            'taux_participation_global' => $participationGlobal,
            'taux_participation_bureaux_comptabilises' => $participationBureaux,
        ];
    }

    private function computeKpis(int $electionId, array $scope = []): array
    {
        $niveau = $this->dominantNiveau($electionId);

        return match ($niveau) {
            'arrondissement' => $this->computeKpisArrondissement($electionId, $scope),
            'village_quartier' => $this->computeKpisVillageQuartier($electionId, $scope),
            default => $this->computeKpisFromLignes($electionId, $scope),
        };
    }

    /* ==================== API SERVICE ==================== */

    public function national(int $electionId): array
    {
        $totaux = $this->computeKpis($electionId);

        $valides = (int) DB::table('proces_verbaux')->where('election_id', $electionId)->whereIn('statut', $this->validPvStatuses())->count();
        $brouillons = (int) DB::table('proces_verbaux')->where('election_id', $electionId)->where('statut', 'brouillon')->count();
        $litigieux = (int) DB::table('proces_verbaux')->where('election_id', $electionId)->where('statut', 'litigieux')->count();

        $progression = [
            'total' => (int) DB::table('proces_verbaux')->where('election_id', $electionId)->count(),
            'valides' => $valides,
            'brouillons' => $brouillons,
            'litigieux' => $litigieux,
        ];

        $depts = DB::table('departements')->select('id', 'nom', 'code')->orderBy('nom')->get();
        $parDept = [];
        foreach ($depts as $d) {
            $k = $this->computeKpis($electionId, ['de.id' => $d->id]);
            $parDept[] = [
                'id' => $d->id,
                'nom' => $d->nom,
                'code' => $d->code,
                'nombre_pv' => $k['nombre_pv_valides'],
                'nombre_pv_valides' => $k['nombre_pv_valides'],
                'inscrits_cena' => $k['inscrits_cena'],
                'inscrits_comptabilises' => $k['inscrits_comptabilises'],
                'couverture_saisie' => $k['couverture_saisie'],
                'nombre_votants' => $k['nombre_votants'],
                'nombre_suffrages_exprimes' => $k['nombre_suffrages_exprimes'],
                'nombre_bulletins_nuls' => $k['nombre_bulletins_nuls'],
                'taux_participation' => $k['taux_participation_global'],
                'taux_participation_global' => $k['taux_participation_global'],
                'taux_participation_bureaux_comptabilises' => $k['taux_participation_bureaux_comptabilises'],
            ];
        }

        return [
            'totaux' => $totaux,
            'progression' => $progression,
            'par_departement' => $parDept,
        ];
    }

    public function departement(int $electionId, int $departementId): array
    {
        $totaux = $this->computeKpis($electionId, ['de.id' => $departementId]);

        $communesRows = DB::table('communes')->where('departement_id', $departementId)->orderBy('nom')->get();
        $communes = [];
        foreach ($communesRows as $c) {
            $k = $this->computeKpis($electionId, ['co.id' => $c->id]);
            $communes[] = [
                'id' => $c->id,
                'nom' => $c->nom,
                'code' => $c->code ?? '',
                'nombre_pv' => $k['nombre_pv_valides'],
                'nombre_pv_valides' => $k['nombre_pv_valides'],
                'inscrits_cena' => $k['inscrits_cena'],
                'inscrits_comptabilises' => $k['inscrits_comptabilises'],
                'couverture_saisie' => $k['couverture_saisie'],
                'nombre_votants' => $k['nombre_votants'],
                'nombre_suffrages_exprimes' => $k['nombre_suffrages_exprimes'],
                'taux_participation' => $k['taux_participation_global'],
                'taux_participation_global' => $k['taux_participation_global'],
            ];
        }

        $circs = DB::table('circonscriptions_electorales as ci')
            // ⚠️ Ici tu utilisais communes.circonscription_id ; on garde la même structure,
            // mais comme la vérité est au niveau arrondissement, mieux vaut passer par arrondissements:
            ->join('arrondissements as ar', 'ar.circonscription_id', '=', 'ci.id')
            ->join('communes as co', 'co.id', '=', 'ar.commune_id')
            ->where('co.departement_id', $departementId)
            ->select('ci.id', 'ci.nom')
            ->distinct()
            ->get();

        $parCirconscription = [];
        foreach ($circs as $circ) {
            $k = $this->computeKpis($electionId, ['ci.id' => $circ->id]);
            $parCirconscription[] = [
                'id' => $circ->id,
                'nom' => $circ->nom,
                'taux_participation' => $k['taux_participation_global'],
            ];
        }

        return [
            'totaux' => $totaux,
            'par_commune' => $communes,
            'par_circonscription' => $parCirconscription,
            'circonscriptions' => $circs,
        ];
    }

    public function circonscription(int $electionId, int $circonscriptionId): array
    {
        $totaux = $this->computeKpis($electionId, ['ci.id' => $circonscriptionId]);

        // ⚠️ même remarque : la vérité est au niveau arrondissement. On liste les communes via arrondissements.
        $communesRows = DB::table('communes as co')
            ->join('arrondissements as ar', 'ar.commune_id', '=', 'co.id')
            ->where('ar.circonscription_id', $circonscriptionId)
            ->select('co.*')
            ->distinct()
            ->orderBy('co.nom')
            ->get();

        $communes = [];
        foreach ($communesRows as $c) {
            $k = $this->computeKpis($electionId, ['co.id' => $c->id]);
            $communes[] = [
                'id' => $c->id,
                'nom' => $c->nom,
                'code' => $c->code ?? '',
                'pv' => $k['nombre_pv_valides'],
                'nombre_pv' => $k['nombre_pv_valides'],
                'nombre_pv_valides' => $k['nombre_pv_valides'],
                'inscrits_cena' => $k['inscrits_cena'],
                'inscrits_comptabilises' => $k['inscrits_comptabilises'],
                'couverture_saisie' => $k['couverture_saisie'],
                'nombre_votants' => $k['nombre_votants'],
                'participation' => $k['taux_participation_global'],
                'taux_participation' => $k['taux_participation_global'],
                'taux_participation_global' => $k['taux_participation_global'],
            ];
        }

        $sieges = (int) (DB::table('circonscriptions_electorales')->where('id', $circonscriptionId)->value('nombre_sieges_total') ?? 0);

        return [
            'totaux' => $totaux,
            'nombre_sieges' => $sieges,
            'par_commune' => $communes,
            'entites' => [],
        ];
    }

    public function commune(int $electionId, int $communeId): array
    {
        $totaux = $this->computeKpis($electionId, ['co.id' => $communeId]);

        $arrRows = DB::table('arrondissements')->where('commune_id', $communeId)->orderBy('nom')->get();
        $arrondissements = [];
        foreach ($arrRows as $a) {
            $k = $this->computeKpis($electionId, ['ar.id' => $a->id]);
            $arrondissements[] = [
                'id' => $a->id,
                'nom' => $a->nom,
                'nombre_pv' => $k['nombre_pv_valides'],
                'nombre_pv_valides' => $k['nombre_pv_valides'],
                'inscrits_cena' => $k['inscrits_cena'],
                'inscrits_comptabilises' => $k['inscrits_comptabilises'],
                'couverture_saisie' => $k['couverture_saisie'],
                'nombre_votants' => $k['nombre_votants'],
                'taux_participation' => $k['taux_participation_global'],
                'taux_participation_global' => $k['taux_participation_global'],
            ];
        }

        $allArrs = DB::table('arrondissements')->select('id', 'nom')->orderBy('nom')->get();
        $selected = DB::table('arrondissements')->find($communeId); // (je ne change pas ta logique ici)

        return [
            'totaux' => $totaux,
            'par_arrondissement' => $arrondissements,
            'arrondissements' => $allArrs,
            'selected' => $selected,
        ];
    }

    public function arrondissement(int $electionId, int $arrondissementId): array
    {
        $totaux = $this->computeKpis($electionId, ['ar.id' => $arrondissementId]);

        $vqRows = DB::table('villages_quartiers')->where('arrondissement_id', $arrondissementId)->orderBy('nom')->get();
        $villages = [];

        foreach ($vqRows as $v) {
            $ins = (int) $this->inscritsCena(['vq.id' => $v->id]);

            $villages[] = [
                'id' => $v->id,
                'nom' => $v->nom,
                'nombre_pv' => 0,
                'nombre_pv_valides' => 0,
                'inscrits_cena' => $ins,
                'inscrits_comptabilises' => 0,
                'couverture_saisie' => 0,
                'nombre_votants' => 0,
                'nombre_suffrages_exprimes' => 0,
                'nombre_bulletins_nuls' => 0,
                'taux_participation' => 0,
                'taux_participation_global' => 0,
                'taux_participation_bureaux_comptabilises' => 0,
            ];
        }

        $allArrs = DB::table('arrondissements')->select('id', 'nom')->orderBy('nom')->get();
        $selected = DB::table('arrondissements')->find($arrondissementId);

        return [
            'totaux' => $totaux,
            'villages' => $villages,
            'arrondissements' => $allArrs,
            'selected' => $selected,
        ];
    }

    public function village(int $electionId, int $villageQuartierId): array
    {
        $ins = (int) $this->inscritsCena(['vq.id' => $villageQuartierId]);

        $totaux = [
            'nombre_pv_valides' => 0,
            'inscrits_cena' => $ins,
            'inscrits_comptabilises' => 0,
            'couverture_saisie' => 0,
            'nombre_votants' => 0,
            'nombre_suffrages_exprimes' => 0,
            'nombre_bulletins_nuls' => 0,
            'taux_participation_global' => 0,
            'taux_participation_bureaux_comptabilises' => 0,
        ];

        $postes = DB::table('postes_vote as pv')
            ->leftJoin('centres_vote as cv', 'cv.id', '=', 'pv.centre_vote_id')
            ->where('pv.village_quartier_id', $villageQuartierId)
            ->orderBy('pv.code')
            ->select([
                'pv.id',
                'pv.nom',
                'pv.code as numero',
                'pv.electeurs_inscrits',
                'cv.nom as centre_nom',
            ])
            ->get()
            ->map(function ($p) {
                return [
                    'id' => $p->id,
                    'nom' => $p->nom,
                    'numero' => $p->numero,
                    'electeurs_inscrits' => $p->electeurs_inscrits ?? 0,
                    'centre_nom' => $p->centre_nom ?? 'Centre N/A',
                    'votants' => 0,
                    'suffrages' => 0,
                    'nuls' => 0,
                ];
            });

        return [
            'totaux' => $totaux,
            'postes' => $postes,
        ];
    }
}
