<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class StatsService
{
    /**
     * Cache local pour éviter de relire le type d'élection à chaque sous-requête.
     */
    private array $includeDiasporaCache = [];
    private array $presidentielleInscritsByCodeCache = [];
    private array $presidentielleInscritsScopesCache = [];

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

    private function includeDiasporaForElection(int $electionId): bool
    {
        if (array_key_exists($electionId, $this->includeDiasporaCache)) {
            return $this->includeDiasporaCache[$electionId];
        }

        $election = DB::table('elections as e')
            ->leftJoin('types_election as te', 'te.id', '=', 'e.type_election_id')
            ->where('e.id', $electionId)
            ->select('e.type', 'te.code as type_ref')
            ->first();

        if (!$election) {
            $this->includeDiasporaCache[$electionId] = false;
            return false;
        }

        $type = strtolower(trim((string) ($election->type ?? '') . ' ' . (string) ($election->type_ref ?? '')));
        $typeNormalise = strtr($type, [
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'à' => 'a', 'â' => 'a',
            'î' => 'i', 'ï' => 'i',
            'ô' => 'o', 'ö' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c',
        ]);

        $includeDiaspora = str_contains($typeNormalise, 'president');
        $this->includeDiasporaCache[$electionId] = $includeDiaspora;

        return $includeDiaspora;
    }

    private function normalizeCodePosteVote(?string $code): string
    {
        return strtoupper(trim((string) $code));
    }

    private function presidentielleInscritsFilePath(): ?string
    {
        $candidates = array_values(array_filter([
            env('PRESIDENTIELLE_INSCRITS_XLSX_PATH'),
            base_path('../election_presidentielle_inscrit.xlsx'),
            dirname(base_path()) . DIRECTORY_SEPARATOR . 'election_presidentielle_inscrit.xlsx',
            base_path('election_presidentielle_inscrit.xlsx'),
            '/var/www/cena-shared/election_presidentielle_inscrit.xlsx',
        ], static fn($path) => is_string($path) && trim($path) !== ''));

        foreach ($candidates as $path) {
            if (is_file($path) && is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    private function parsePresidentielleInscritsExcel(string $path): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return [];
        }

        try {
            $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
            if ($sheetXml === false) {
                return [];
            }

            $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
            $sharedStrings = [];

            if ($sharedStringsXml !== false) {
                $sharedDom = new \DOMDocument();
                if (@$sharedDom->loadXML($sharedStringsXml)) {
                    $sharedXpath = new \DOMXPath($sharedDom);
                    $sharedXpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

                    foreach ($sharedXpath->query('//x:si') as $siNode) {
                        $text = '';
                        foreach ($sharedXpath->query('.//x:t', $siNode) as $tNode) {
                            $text .= (string) $tNode->nodeValue;
                        }
                        $sharedStrings[] = $text;
                    }
                }
            }

            $sheetDom = new \DOMDocument();
            if (!@$sheetDom->loadXML($sheetXml)) {
                return [];
            }

            $xpath = new \DOMXPath($sheetDom);
            $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

            $inscritsByCode = [];

            foreach ($xpath->query('//x:sheetData/x:row') as $rowNode) {
                $rowRefNode = $rowNode->attributes->getNamedItem('r');
                $rowIndex = (int) ($rowRefNode ? $rowRefNode->nodeValue : 0);
                if ($rowIndex <= 1) {
                    continue;
                }

                $code = '';
                $total = 0;

                foreach ($xpath->query('./x:c', $rowNode) as $cellNode) {
                    $refNode = $cellNode->attributes->getNamedItem('r');
                    $cellRef = (string) ($refNode ? $refNode->nodeValue : '');
                    $column = preg_replace('/\d+/', '', $cellRef);
                    if ($column !== 'F' && $column !== 'J') {
                        continue;
                    }

                    $typeNode = $cellNode->attributes->getNamedItem('t');
                    $cellType = (string) ($typeNode ? $typeNode->nodeValue : '');
                    $value = '';

                    if ($cellType === 's') {
                        $valueNode = $xpath->query('./x:v', $cellNode)->item(0);
                        $index = (int) ($valueNode ? $valueNode->nodeValue : -1);
                        if ($index >= 0 && array_key_exists($index, $sharedStrings)) {
                            $value = (string) $sharedStrings[$index];
                        }
                    } elseif ($cellType === 'inlineStr') {
                        $inlineNode = $xpath->query('./x:is/x:t', $cellNode)->item(0);
                        $value = (string) ($inlineNode ? $inlineNode->nodeValue : '');
                    } else {
                        $valueNode = $xpath->query('./x:v', $cellNode)->item(0);
                        $value = (string) ($valueNode ? $valueNode->nodeValue : '');
                    }

                    if ($column === 'F') {
                        $code = $this->normalizeCodePosteVote($value);
                        continue;
                    }

                    $clean = preg_replace('/[^\d\-]/', '', (string) $value);
                    $total = (int) ($clean === '' ? 0 : $clean);
                }

                if ($code !== '') {
                    $inscritsByCode[$code] = $total;
                }
            }

            return $inscritsByCode;
        } finally {
            $zip->close();
        }
    }

    private function presidentielleInscritsByCode(int $electionId): ?array
    {
        if (array_key_exists($electionId, $this->presidentielleInscritsByCodeCache)) {
            return $this->presidentielleInscritsByCodeCache[$electionId];
        }

        if (!$this->includeDiasporaForElection($electionId)) {
            $this->presidentielleInscritsByCodeCache[$electionId] = null;
            return null;
        }

        $path = $this->presidentielleInscritsFilePath();
        if ($path === null) {
            $this->presidentielleInscritsByCodeCache[$electionId] = null;
            return null;
        }

        $inscritsByCode = $this->parsePresidentielleInscritsExcel($path);
        if (empty($inscritsByCode)) {
            $this->presidentielleInscritsByCodeCache[$electionId] = null;
            return null;
        }

        $this->presidentielleInscritsByCodeCache[$electionId] = $inscritsByCode;
        return $inscritsByCode;
    }

    private function sumInscritsFromReferenceRows(iterable $rows, array $referenceByCode): int
    {
        $total = 0;

        foreach ($rows as $row) {
            $code = $this->normalizeCodePosteVote($row->code ?? null);
            $fallback = (int) ($row->electeurs_inscrits ?? 0);

            if ($code !== '' && array_key_exists($code, $referenceByCode)) {
                $total += (int) $referenceByCode[$code];
            } else {
                $total += $fallback;
            }
        }

        return $total;
    }

    private function presidentielleInscritsParScope(int $electionId): ?array
    {
        if (array_key_exists($electionId, $this->presidentielleInscritsScopesCache)) {
            return $this->presidentielleInscritsScopesCache[$electionId];
        }

        $referenceByCode = $this->presidentielleInscritsByCode($electionId);
        if ($referenceByCode === null) {
            $this->presidentielleInscritsScopesCache[$electionId] = null;
            return null;
        }

        $rows = DB::table('postes_vote as pv')
            ->join('villages_quartiers as vq', 'vq.id', '=', 'pv.village_quartier_id')
            ->join('arrondissements as ar', 'ar.id', '=', 'vq.arrondissement_id')
            ->join('communes as co', 'co.id', '=', 'ar.commune_id')
            ->join('departements as de', 'de.id', '=', 'co.departement_id')
            ->leftJoin('circonscriptions_electorales as ci', 'ci.id', '=', 'ar.circonscription_id')
            ->where('pv.actif', true)
            ->select([
                'pv.code',
                'pv.electeurs_inscrits',
                'de.id as departement_id',
                'co.id as commune_id',
                'ci.id as circonscription_id',
                'ar.id as arrondissement_id',
                'vq.id as village_quartier_id',
            ])
            ->get();

        $agg = [
            'total' => 0,
            'de.id' => [],
            'co.id' => [],
            'ci.id' => [],
            'ar.id' => [],
            'vq.id' => [],
        ];

        foreach ($rows as $row) {
            $code = $this->normalizeCodePosteVote($row->code ?? null);
            $fallback = (int) ($row->electeurs_inscrits ?? 0);
            $inscrits = ($code !== '' && array_key_exists($code, $referenceByCode))
                ? (int) $referenceByCode[$code]
                : $fallback;

            $agg['total'] += $inscrits;

            foreach (['de.id' => 'departement_id', 'co.id' => 'commune_id', 'ci.id' => 'circonscription_id', 'ar.id' => 'arrondissement_id', 'vq.id' => 'village_quartier_id'] as $scopeKey => $field) {
                $scopeValue = $row->{$field};
                if ($scopeValue === null) {
                    continue;
                }

                $scopeId = (int) $scopeValue;
                if (!isset($agg[$scopeKey][$scopeId])) {
                    $agg[$scopeKey][$scopeId] = 0;
                }
                $agg[$scopeKey][$scopeId] += $inscrits;
            }
        }

        $this->presidentielleInscritsScopesCache[$electionId] = $agg;
        return $agg;
    }

    private function inscritsCenaPresidentielle(int $electionId, array $scope = []): ?int
    {
        $aggregates = $this->presidentielleInscritsParScope($electionId);
        if ($aggregates === null) {
            return null;
        }

        if (empty($scope)) {
            return (int) ($aggregates['total'] ?? 0);
        }

        if (count($scope) === 1) {
            $scopeKey = array_key_first($scope);
            $scopeValue = (int) ($scope[$scopeKey] ?? 0);

            if (in_array($scopeKey, ['de.id', 'co.id', 'ci.id', 'ar.id', 'vq.id'], true)) {
                return (int) ($aggregates[$scopeKey][$scopeValue] ?? 0);
            }
        }

        $referenceByCode = $this->presidentielleInscritsByCode($electionId);
        if ($referenceByCode === null) {
            return null;
        }

        $q = DB::table('postes_vote as pv')
            ->join('villages_quartiers as vq', 'vq.id', '=', 'pv.village_quartier_id')
            ->join('arrondissements as ar', 'ar.id', '=', 'vq.arrondissement_id')
            ->join('communes as co', 'co.id', '=', 'ar.commune_id')
            ->join('departements as de', 'de.id', '=', 'co.departement_id')
            ->leftJoin('circonscriptions_electorales as ci', 'ci.id', '=', 'ar.circonscription_id')
            ->where('pv.actif', true);

        $this->applyScope($q, $scope);

        $rows = $q->select('pv.code', 'pv.electeurs_inscrits')->get();
        return $this->sumInscritsFromReferenceRows($rows, $referenceByCode);
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
    private function inscritsCena(int $electionId, array $scope = []): int
    {
        $includeDiaspora = $this->includeDiasporaForElection($electionId);

        if ($includeDiaspora) {
            $fromExcel = $this->inscritsCenaPresidentielle($electionId, $scope);
            if ($fromExcel !== null) {
                return $fromExcel;
            }
        }

        $q = DB::table('postes_vote as pv')
            ->join('villages_quartiers as vq', 'vq.id', '=', 'pv.village_quartier_id')
            ->join('arrondissements as ar', 'ar.id', '=', 'vq.arrondissement_id')
            ->join('communes as co', 'co.id', '=', 'ar.commune_id')
            ->join('departements as de', 'de.id', '=', 'co.departement_id')
            ->leftJoin('circonscriptions_electorales as ci', 'ci.id', '=', 'ar.circonscription_id')
            ->where('pv.actif', true)
            ->selectRaw('COALESCE(SUM(pv.electeurs_inscrits), 0) as total');

        if (!$includeDiaspora) {
            // ✅ Exclure circonscription diaspora
            $this->excludeDiasporaArr($q);

            // ✅ Exclure département diaspora
            $this->excludeDiasporaDept($q);
        }

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
        $includeDiaspora = $this->includeDiasporaForElection($electionId);
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

        if (!$includeDiaspora) {
            // ✅ Exclure circonscription diaspora
            $this->excludeDiasporaArr($base);

            // ✅ Exclure département diaspora
            $this->excludeDiasporaDept($base);
        }

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
        $inscritsCena = $this->inscritsCena($electionId, $scope);

        $dedup = $this->dedupLignesArrondissement($electionId, $scope);

        $voixSub = DB::table('pv_ligne_resultats as r')
            ->selectRaw('r.pv_ligne_id, COALESCE(SUM(r.nombre_voix), 0) as total_voix')
            ->groupBy('r.pv_ligne_id');

        // inscrits comptabilisés = postes_vote des villages présents dans dedup
        $referenceByCode = $this->presidentielleInscritsByCode($electionId);
        if ($referenceByCode !== null) {
            $inscritsRows = DB::query()->fromSub($dedup, 'd')
                ->join('postes_vote as pv', 'pv.village_quartier_id', '=', 'd.village_quartier_id')
                ->where('pv.actif', true)
                ->select('pv.code', 'pv.electeurs_inscrits')
                ->get();

            $inscritsComp = $this->sumInscritsFromReferenceRows($inscritsRows, $referenceByCode);
        } else {
            $inscritsComp = (int) DB::query()->fromSub($dedup, 'd')
                ->join('postes_vote as pv', 'pv.village_quartier_id', '=', 'd.village_quartier_id')
                ->where('pv.actif', true)
                ->selectRaw('COALESCE(SUM(pv.electeurs_inscrits), 0) as total')
                ->value('total');
        }

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
        $includeDiaspora = $this->includeDiasporaForElection($electionId);
        $totaux = $this->computeKpis($electionId);

        $validesQ = DB::table('proces_verbaux as pv')
            ->join('arrondissements as ar', 'ar.id', '=', DB::raw('pv.niveau_id::int'))
            ->join('communes as co', 'co.id', '=', 'ar.commune_id')
            ->join('departements as de', 'de.id', '=', 'co.departement_id')
            ->where('pv.election_id', $electionId)
            ->where('pv.niveau', 'arrondissement')
            ->whereIn('pv.statut', $this->validPvStatuses());
        if (!$includeDiaspora) {
            $validesQ->where(function ($q) {
                $q->whereNull('ar.circonscription_id')->orWhere('ar.circonscription_id', '<>', 25);
            })->where('de.id', '<>', 13);
        }
        $valides = (int) $validesQ->count();

        $brouillonsQ = DB::table('proces_verbaux as pv')
            ->join('arrondissements as ar', 'ar.id', '=', DB::raw('pv.niveau_id::int'))
            ->join('communes as co', 'co.id', '=', 'ar.commune_id')
            ->join('departements as de', 'de.id', '=', 'co.departement_id')
            ->where('pv.election_id', $electionId)
            ->where('pv.niveau', 'arrondissement')
            ->where('pv.statut', 'brouillon');
        if (!$includeDiaspora) {
            $brouillonsQ->where(function ($q) {
                $q->whereNull('ar.circonscription_id')->orWhere('ar.circonscription_id', '<>', 25);
            })->where('de.id', '<>', 13);
        }
        $brouillons = (int) $brouillonsQ->count();

        $litigieuxQ = DB::table('proces_verbaux as pv')
            ->join('arrondissements as ar', 'ar.id', '=', DB::raw('pv.niveau_id::int'))
            ->join('communes as co', 'co.id', '=', 'ar.commune_id')
            ->join('departements as de', 'de.id', '=', 'co.departement_id')
            ->where('pv.election_id', $electionId)
            ->where('pv.niveau', 'arrondissement')
            ->where('pv.statut', 'litigieux');
        if (!$includeDiaspora) {
            $litigieuxQ->where(function ($q) {
                $q->whereNull('ar.circonscription_id')->orWhere('ar.circonscription_id', '<>', 25);
            })->where('de.id', '<>', 13);
        }
        $litigieux = (int) $litigieuxQ->count();

        $progression = [
            'total' => $valides + $brouillons + $litigieux,
            'valides' => $valides,
            'brouillons' => $brouillons,
            'litigieux' => $litigieux,
        ];

        $deptsQ = DB::table('departements')
            ->select('id', 'nom', 'code')
            ->orderBy('nom');
        if (!$includeDiaspora) {
            $deptsQ->where('id', '<>', 13);
        }
        $depts = $deptsQ->get();
            
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
        $includeDiaspora = $this->includeDiasporaForElection($electionId);

        // ✅ Sécurité : si c'est le département Diaspora, retourner vide uniquement hors présidentielle
        if (!$includeDiaspora && $departementId === 13) {
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

        $circsQ = DB::table('circonscriptions_electorales as ci')
            ->join('arrondissements as ar', 'ar.circonscription_id', '=', 'ci.id')
            ->join('communes as co', 'co.id', '=', 'ar.commune_id')
            ->where('co.departement_id', $departementId)
            ->select('ci.id', 'ci.nom')
            ->distinct()
            ->orderBy('ci.nom');
        if (!$includeDiaspora) {
            $circsQ->where('ci.numero', '<=', 24);
        }
        $circs = $circsQ->get();

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
        $includeDiaspora = $this->includeDiasporaForElection($electionId);

        // ✅ sécurité diaspora hors présidentielle
        if (!$includeDiaspora && $circonscriptionId === 25) {
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
            $ins = (int) $this->inscritsCena($electionId, ['vq.id' => $v->id]);
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
        $ins = (int) $this->inscritsCena($electionId, ['vq.id' => $villageQuartierId]);
        $k = $this->computeKpis($electionId, ['vq.id' => $villageQuartierId]);
        $referenceByCode = $this->presidentielleInscritsByCode($electionId);

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
            ->map(function ($p) use ($referenceByCode) {
                $code = $this->normalizeCodePosteVote($p->numero ?? null);
                $inscrits = ($referenceByCode !== null && $code !== '' && array_key_exists($code, $referenceByCode))
                    ? (int) $referenceByCode[$code]
                    : (int) ($p->electeurs_inscrits ?? 0);

                return [
                    'id' => $p->id,
                    'nom' => $p->nom,
                    'numero' => $p->numero,
                    'electeurs_inscrits' => $inscrits,
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
