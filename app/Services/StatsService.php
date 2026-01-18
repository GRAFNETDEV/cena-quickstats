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

    /**
     * ✅ Exclure diaspora (législative - circonscription)
     */
    private function excludeDiasporaArr($q)
    {
        // diaspora = circonscription_id = 25
        return $q->where(function ($w) {
            $w->whereNull('ar.circonscription_id')
              ->orWhere('ar.circonscription_id', '<>', 25);
        });
    }

    /**
     * ✅ Exclure le département Diaspora (id = 13)
     */
    private function excludeDiasporaDept($q)
    {
        // Exclure le département avec id = 13 (Diaspora)
        return $q->where('de.id', '<>', 13);
    }

    /**
     * Inscrits CENA (référence postes_vote)
     * ✅ circonscription au niveau arrondissement (ar.circonscription_id)
     * ✅ Exclure diaspora (circonscription ET département)
     */
    private function inscritsCena(array $scope = []): int
    {
        $q = DB::table('postes_vote as pv')
            ->join('villages_quartiers as vq', 'vq.id', '=', 'pv.village_quartier_id')
            ->join('arrondissements as ar', 'ar.id', '=', 'vq.arrondissement_id')
            ->join('communes as co', 'co.id', '=', 'ar.commune_id')
            ->join('departements as de', 'de.id', '=', 'co.departement_id')
            ->leftJoin('circonscriptions_electorales as ci', 'ci.id', '=', 'ar.circonscription_id')
            ->selectRaw('COALESCE(SUM(pv.electeurs_inscrits), 0) as total');

        // ✅ Exclure circonscription diaspora
        $this->excludeDiasporaArr($q);
        
        // ✅ Exclure département diaspora
        $this->excludeDiasporaDept($q);

        $this->applyScope($q, $scope);
        return (int) $q->value('total');
    }

    /**
     * ✅ Base DEDUP : dernière ligne par (arrondissement, village_quartier)
     * sur TOUS les PV arrondissement valides/publies
     *
     * Ordre : pv.created_at DESC puis pl.created_at DESC puis pl.id DESC
     */
    private function dedupLignesArrondissement(int $electionId, array $scope = [])
    {
        $validStatuses = $this->validPvStatuses();

        $base = DB::table('proces_verbaux as pv')
            ->join('pv_lignes as pl', 'pl.proces_verbal_id', '=', 'pv.id')
            ->join('villages_quartiers as vq', 'vq.id', '=', 'pl.village_quartier_id')
            ->join('arrondissements as ar', 'ar.id', '=', 'vq.arrondissement_id')
            ->join('communes as co', 'co.id', '=', 'ar.commune_id')
            ->join('departements as de', 'de.id', '=', 'co.departement_id')
            ->leftJoin('circonscriptions_electorales as ci', 'ci.id', '=', 'ar.circonscription_id')
            ->where('pv.election_id', $electionId)
            ->where('pv.niveau', 'arrondissement')
            ->whereIn('pv.statut', $validStatuses)
            ->whereNotNull('pl.village_quartier_id');

        // ✅ Exclure circonscription diaspora
        $this->excludeDiasporaArr($base);
        
        // ✅ Exclure département diaspora
        $this->excludeDiasporaDept($base);

        // Scope autorisé (tes vues utilisent de.id/co.id/ci.id/ar.id/vq.id)
        $allowed = array_intersect_key($scope, array_flip(['de.id', 'co.id', 'ci.id', 'ar.id', 'vq.id']));
        $this->applyScope($base, $allowed);

        return DB::query()->fromSub(
            $base->selectRaw("
                de.id as departement_id,
                co.id as commune_id,
                ci.id as circonscription_id,
                ar.id as arrondissement_id,
                vq.id as village_quartier_id,

                pv.id as proces_verbal_id,
                pv.created_at as pv_created_at,

                pl.id as pv_ligne_id,
                pl.created_at as ligne_created_at,
                COALESCE(pl.bulletins_nuls,0) as bulletins_nuls,

                ROW_NUMBER() OVER (
                    PARTITION BY ar.id, pl.village_quartier_id
                    ORDER BY pv.created_at DESC NULLS LAST, pl.created_at DESC NULLS LAST, pl.id DESC
                ) as rn
            "),
            'd0'
        )->where('rn', 1);
    }

    /**
     * ✅ KPI principal (corrigé) : calcul depuis pv_lignes + pv_ligne_resultats
     * - suffrages = somme des voix sur lignes retenues
     * - nuls = somme bulletins_nuls sur lignes retenues
     * - votants = suffrages + nuls
     * - nombre_pv_valides = COUNT(DISTINCT proces_verbal_id) des lignes retenues
     */
    private function computeKpisFromLignes(int $electionId, array $scope = []): array
    {
        $inscritsCena = $this->inscritsCena($scope);

        $dedup = $this->dedupLignesArrondissement($electionId, $scope);

        $voixSub = DB::table('pv_ligne_resultats as r')
            ->selectRaw('r.pv_ligne_id, COALESCE(SUM(r.nombre_voix), 0) as total_voix')
            ->groupBy('r.pv_ligne_id');

        // inscrits comptabilisés = postes_vote des villages présents dans dedup
        $inscritsComp = (int) DB::query()->fromSub($dedup, 'd')
            ->join('postes_vote as pv', 'pv.village_quartier_id', '=', 'd.village_quartier_id')
            ->selectRaw('COALESCE(SUM(pv.electeurs_inscrits), 0) as total')
            ->value('total');

        $agg = DB::query()->fromSub($dedup, 'd')
            ->leftJoinSub($voixSub, 'voix', 'voix.pv_ligne_id', '=', 'd.pv_ligne_id')
            ->selectRaw('COUNT(DISTINCT d.proces_verbal_id) as nb_pv')
            ->selectRaw('COALESCE(SUM(COALESCE(voix.total_voix,0)), 0) as suffrages')
            ->selectRaw('COALESCE(SUM(COALESCE(d.bulletins_nuls,0)), 0) as nuls')
            ->selectRaw('COALESCE(SUM(COALESCE(voix.total_voix,0) + COALESCE(d.bulletins_nuls,0)), 0) as votants')
            ->first();

        $votants = (int)($agg->votants ?? 0);
        $suffrages = (int)($agg->suffrages ?? 0);
        $nuls = (int)($agg->nuls ?? 0);

        $couverture = $inscritsCena > 0 ? round(($inscritsComp / $inscritsCena) * 100, 2) : 0.0;
        $participationGlobal = $inscritsCena > 0 ? round(($votants / $inscritsCena) * 100, 2) : 0.0;
        $participationBureaux = $inscritsComp > 0 ? round(($votants / $inscritsComp) * 100, 2) : 0.0;

        return [
            'nombre_pv_valides' => (int)($agg->nb_pv ?? 0),
            'inscrits_cena' => $inscritsCena,
            'inscrits_comptabilises' => $inscritsComp,
            'couverture_saisie' => $couverture,
            'nombre_votants' => $votants,
            'nombre_suffrages_exprimes' => $suffrages,
            'nombre_bulletins_nuls' => $nuls,
            'taux_participation_global' => $participationGlobal,
            'taux_participation_bureaux_comptabilises' => $participationBureaux,
        ];
    }

    /**
     * ✅ Ici on force toujours les KPI depuis lignes (plus fiable dans ton cas)
     */
    private function computeKpis(int $electionId, array $scope = []): array
    {
        return $this->computeKpisFromLignes($electionId, $scope);
    }

    /* ==================== API SERVICE ==================== */

    public function national(int $electionId): array
    {
        $totaux = $this->computeKpis($electionId);

        // Progression (PV) : on exclut diaspora aussi
        $valides = (int) DB::table('proces_verbaux as pv')
            ->join('arrondissements as ar', 'ar.id', '=', DB::raw('pv.niveau_id::int'))
            ->join('communes as co', 'co.id', '=', 'ar.commune_id')
            ->join('departements as de', 'de.id', '=', 'co.departement_id')
            ->where('pv.election_id', $electionId)
            ->where('pv.niveau', 'arrondissement')
            ->whereIn('pv.statut', $this->validPvStatuses())
            ->where(function ($q) {
                $q->whereNull('ar.circonscription_id')->orWhere('ar.circonscription_id', '<>', 25);
            })
            ->where('de.id', '<>', 13) // ✅ Exclure département Diaspora
            ->count();

        $brouillons = (int) DB::table('proces_verbaux as pv')
            ->join('arrondissements as ar', 'ar.id', '=', DB::raw('pv.niveau_id::int'))
            ->join('communes as co', 'co.id', '=', 'ar.commune_id')
            ->join('departements as de', 'de.id', '=', 'co.departement_id')
            ->where('pv.election_id', $electionId)
            ->where('pv.niveau', 'arrondissement')
            ->where('pv.statut', 'brouillon')
            ->where(function ($q) {
                $q->whereNull('ar.circonscription_id')->orWhere('ar.circonscription_id', '<>', 25);
            })
            ->where('de.id', '<>', 13) // ✅ Exclure département Diaspora
            ->count();

        $litigieux = (int) DB::table('proces_verbaux as pv')
            ->join('arrondissements as ar', 'ar.id', '=', DB::raw('pv.niveau_id::int'))
            ->join('communes as co', 'co.id', '=', 'ar.commune_id')
            ->join('departements as de', 'de.id', '=', 'co.departement_id')
            ->where('pv.election_id', $electionId)
            ->where('pv.niveau', 'arrondissement')
            ->where('pv.statut', 'litigieux')
            ->where(function ($q) {
                $q->whereNull('ar.circonscription_id')->orWhere('ar.circonscription_id', '<>', 25);
            })
            ->where('de.id', '<>', 13) // ✅ Exclure département Diaspora
            ->count();

        $progression = [
            'total' => $valides + $brouillons + $litigieux,
            'valides' => $valides,
            'brouillons' => $brouillons,
            'litigieux' => $litigieux,
        ];

        // ✅ Exclure le département Diaspora de la liste
        $depts = DB::table('departements')
            ->select('id', 'nom', 'code')
            ->where('id', '<>', 13) // ✅ Exclure Diaspora
            ->orderBy('nom')
            ->get();
            
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
        // ✅ Sécurité : si c'est le département Diaspora, retourner vide
        if ($departementId === 13) {
            return [
                'totaux' => [],
                'par_commune' => [],
                'par_circonscription' => [],
                'circonscriptions' => [],
            ];
        }

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

        // ✅ Circonscriptions du département via arrondissements (et exclure diaspora)
        $circs = DB::table('circonscriptions_electorales as ci')
            ->join('arrondissements as ar', 'ar.circonscription_id', '=', 'ci.id')
            ->join('communes as co', 'co.id', '=', 'ar.commune_id')
            ->where('co.departement_id', $departementId)
            ->where('ci.numero', '<=', 24)
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
        // ✅ sécurité diaspora
        if ($circonscriptionId === 25) {
            $circonscriptionId = 0;
        }

        $totaux = $this->computeKpis($electionId, ['ci.id' => $circonscriptionId]);

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
        $selected = DB::table('arrondissements')->find($communeId); // (je garde ta logique)

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

        // ✅ Liste des villages + stats réelles (plus de 0 partout)
        $vqRows = DB::table('villages_quartiers')->where('arrondissement_id', $arrondissementId)->orderBy('nom')->get();

        $villages = [];
        foreach ($vqRows as $v) {
            $ins = (int) $this->inscritsCena(['vq.id' => $v->id]);
            $k = $this->computeKpis($electionId, ['vq.id' => $v->id]);

            $villages[] = [
                'id' => $v->id,
                'nom' => $v->nom,
                'nombre_pv' => $k['nombre_pv_valides'],
                'nombre_pv_valides' => $k['nombre_pv_valides'],
                'inscrits_cena' => $ins,
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
        $k = $this->computeKpis($electionId, ['vq.id' => $villageQuartierId]);

        $totaux = [
            'nombre_pv_valides' => $k['nombre_pv_valides'],
            'inscrits_cena' => $ins,
            'inscrits_comptabilises' => $k['inscrits_comptabilises'],
            'couverture_saisie' => $k['couverture_saisie'],
            'nombre_votants' => $k['nombre_votants'],
            'nombre_suffrages_exprimes' => $k['nombre_suffrages_exprimes'],
            'nombre_bulletins_nuls' => $k['nombre_bulletins_nuls'],
            'taux_participation_global' => $k['taux_participation_global'],
            'taux_participation_bureaux_comptabilises' => $k['taux_participation_bureaux_comptabilises'],
        ];

        // (Tu pourras plus tard alimenter par poste/centre si tu as l'info au niveau poste.)
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