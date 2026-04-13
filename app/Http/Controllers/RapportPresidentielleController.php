<?php

namespace App\Http\Controllers;

use App\Services\ResultatsPresidentielleService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RapportPresidentielleController extends Controller
{
    private ResultatsPresidentielleService $presidentielle;

    public function __construct(ResultatsPresidentielleService $presidentielle)
    {
        $this->presidentielle = $presidentielle;
    }

    public function index(Request $request)
    {
        [$election, $electionsPresidentielles] = $this->resolveElection($request);

        $niveau = $this->sanitizeNiveau((string) $request->get('niveau', 'national'));
        $departementId = (int) $request->get('departement_id');
        $communeId = (int) $request->get('commune_id');

        $report = $this->buildReport(
            (int) $election->id,
            $electionsPresidentielles,
            $niveau,
            $departementId,
            $communeId
        );

        return view('rapports.presidentielle.index', $report);
    }

    public function pdf(Request $request)
    {
        [$election, $electionsPresidentielles] = $this->resolveElection($request);

        $niveau = $this->sanitizeNiveau((string) $request->get('niveau', 'national'));
        $departementId = (int) $request->get('departement_id');
        $communeId = (int) $request->get('commune_id');

        set_time_limit(300);

        $report = $this->buildReport(
            (int) $election->id,
            $electionsPresidentielles,
            $niveau,
            $departementId,
            $communeId
        );

        $pdf = Pdf::loadView('rapports.presidentielle.pdf', $report)
            ->setPaper('A4', 'portrait')
            ->setOption('dpi', 96)
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('isRemoteEnabled', false)
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isPhpEnabled', true);

        $filename = 'rapport_presidentielle_' . $niveau . '_' . date('Y-m-d_His') . '.pdf';

        return $pdf->download($filename);
    }

    private function buildReport(
        int $electionId,
        Collection $electionsPresidentielles,
        string $niveau,
        int $departementId,
        int $communeId
    ): array {
        $election = DB::table('elections')->find($electionId);
        if (!$election) {
            abort(404, 'Élection introuvable');
        }

        $data = $this->presidentielle->getResultatsParDepartement($electionId);
        $compilation = $this->presidentielle->compilerResultats($electionId);

        $departements = collect($data['departements'] ?? [])->values();
        $communes = collect($data['communes'] ?? [])->values();
        $entites = collect($data['entites'] ?? [])->values();

        $matriceCommunes = $data['matrice_communes'] ?? [];
        $matriceDepartements = $data['matrice_departements'] ?? [];
        $totauxNationaux = $data['totaux_par_entite'] ?? [];

        $communesScope = $communes;
        if ($niveau === 'departement' && $departementId > 0) {
            $communesScope = $communes->where('departement_id', $departementId)->values();
        } elseif ($niveau === 'commune' && $communeId > 0) {
            $communesScope = $communes->where('id', $communeId)->values();
        }

        $departementsScope = $departements;
        if ($niveau === 'departement' && $departementId > 0) {
            $departementsScope = $departements->where('id', $departementId)->values();
        } elseif ($niveau === 'commune' && $communeId > 0) {
            $communeSelectionnee = $communes->firstWhere('id', $communeId);
            $depId = (int) ($communeSelectionnee->departement_id ?? 0);
            $departementsScope = $depId > 0
                ? $departements->where('id', $depId)->values()
                : collect();
        }

        $totVoixScope = [];
        $communesGagnees = [];
        foreach ($entites as $entite) {
            $totVoixScope[$entite->id] = 0;
            $communesGagnees[$entite->id] = 0;
        }

        $matriceCommunesScope = [];
        foreach ($communesScope as $commune) {
            $ligne = $matriceCommunes[$commune->id] ?? [
                'info' => $commune,
                'resultats' => [],
                'total_voix' => 0,
            ];

            $resultats = $ligne['resultats'] ?? [];
            $maxVoix = 0;

            foreach ($entites as $entite) {
                $voix = (int) ($resultats[$entite->id]['voix'] ?? 0);
                $totVoixScope[$entite->id] += $voix;
                if ($voix > $maxVoix) {
                    $maxVoix = $voix;
                }
            }

            if ($maxVoix > 0) {
                foreach ($entites as $entite) {
                    $voix = (int) ($resultats[$entite->id]['voix'] ?? 0);
                    if ($voix === $maxVoix) {
                        $communesGagnees[$entite->id]++;
                    }
                }
            }

            $matriceCommunesScope[] = [
                'info' => $commune,
                'resultats' => $resultats,
                'total_voix' => (int) ($ligne['total_voix'] ?? 0),
            ];
        }

        $matriceDepartementsScope = [];
        foreach ($departementsScope as $departement) {
            $ligne = $matriceDepartements[$departement->id] ?? [
                'info' => $departement,
                'resultats' => [],
                'total_voix' => 0,
            ];

            $matriceDepartementsScope[] = [
                'info' => $departement,
                'resultats' => $ligne['resultats'] ?? [],
                'total_voix' => (int) ($ligne['total_voix'] ?? 0),
            ];
        }

        $totalVoixScope = array_sum($totVoixScope);
        $majoriteAbsolueScopeVoix = $totalVoixScope > 0 ? intdiv($totalVoixScope, 2) + 1 : 0;

        $classementNationalByEntite = [];
        foreach ($compilation['classement_national'] ?? [] as $row) {
            $classementNationalByEntite[(int) $row['entite_id']] = $row;
        }

        $tableScope = [];
        foreach ($entites as $entite) {
            $voixScope = (int) ($totVoixScope[$entite->id] ?? 0);
            $pctScope = $totalVoixScope > 0 ? ($voixScope / $totalVoixScope) * 100 : 0.0;
            $totauxNatEntite = $totauxNationaux[$entite->id] ?? ['voix' => 0, 'pourcentage_national' => 0];
            $nat = $classementNationalByEntite[$entite->id] ?? null;

            $tableScope[] = [
                'entite' => $entite,
                'voix_scope' => $voixScope,
                'pct_scope' => (float) $pctScope,
                'communes_gagnees' => (int) ($communesGagnees[$entite->id] ?? 0),
                'voix_national' => (int) ($totauxNatEntite['voix'] ?? 0),
                'pct_national' => (float) ($totauxNatEntite['pourcentage_national'] ?? 0),
                'rang_national' => (int) ($nat['rang'] ?? 0),
                'statut_national' => (string) ($nat['statut'] ?? 'non_qualifie'),
            ];
        }

        usort($tableScope, static function (array $a, array $b): int {
            if ($a['voix_scope'] === $b['voix_scope']) {
                $aNumeroListe = (int) ($a['entite']->numero_liste ?? 9999);
                $bNumeroListe = (int) ($b['entite']->numero_liste ?? 9999);
                if ($aNumeroListe === $bNumeroListe) {
                    return (int) $a['entite']->id <=> (int) $b['entite']->id;
                }
                return $aNumeroListe <=> $bNumeroListe;
            }
            return $b['voix_scope'] <=> $a['voix_scope'];
        });

        foreach ($tableScope as $index => &$row) {
            $row['rang_scope'] = $index + 1;
        }
        unset($row);

        $communesRef = $communes;
        if ($departementId > 0) {
            $communesRef = $communes->where('departement_id', $departementId)->values();
        }

        $titre = $this->buildTitre($niveau, $departementId, $communeId, $departements, $communes);

        $topCommunes = collect($matriceCommunesScope)
            ->sortByDesc('total_voix')
            ->take(10)
            ->values()
            ->all();

        return [
            'election' => $election,
            'electionsPresidentielles' => $electionsPresidentielles,
            'niveau' => $niveau,
            'titre' => $titre,
            'filters' => [
                'departement_id' => $departementId,
                'commune_id' => $communeId,
            ],
            'departements' => $departements,
            'communesRef' => $communesRef,
            'entites' => $entites,
            'tableScope' => $tableScope,
            'totalVoixScope' => $totalVoixScope,
            'majoriteAbsolueScopeVoix' => $majoriteAbsolueScopeVoix,
            'matriceDepartementsScope' => $matriceDepartementsScope,
            'matriceCommunesScope' => $matriceCommunesScope,
            'compilation' => $compilation,
            'regles' => $data['regles'] ?? [],
            'topCommunes' => $topCommunes,
            'mention_provisoire' => 'VERSION PROVISOIRE – Issue de la plateforme de compilation de GRAFNET',
        ];
    }

    private function sanitizeNiveau(string $niveau): string
    {
        return in_array($niveau, ['national', 'departement', 'commune'], true)
            ? $niveau
            : 'national';
    }

    private function resolveElection(Request $request): array
    {
        $elections = DB::table('elections as e')
            ->leftJoin('types_election as te', 'te.id', '=', 'e.type_election_id')
            ->select('e.*', 'te.code as type_ref')
            ->orderByDesc('e.date_scrutin')
            ->orderByDesc('e.id')
            ->get();

        $electionsPresidentielles = $elections
            ->filter(fn($election) => $this->typeElection($election) === 'presidentielle')
            ->values();

        if ($electionsPresidentielles->isEmpty()) {
            abort(404, 'Aucune élection présidentielle trouvée');
        }

        $requestedId = (int) $request->get('election_id');
        if ($requestedId > 0) {
            $requested = $electionsPresidentielles->firstWhere('id', $requestedId);
            if ($requested) {
                session(['election_active' => (int) $requested->id]);
                return [$requested, $electionsPresidentielles];
            }
        }

        $sessionId = (int) session('election_active');
        if ($sessionId > 0) {
            $sessionElection = $electionsPresidentielles->firstWhere('id', $sessionId);
            if ($sessionElection) {
                return [$sessionElection, $electionsPresidentielles];
            }
        }

        $fallback = $electionsPresidentielles->first();
        session(['election_active' => (int) $fallback->id]);

        return [$fallback, $electionsPresidentielles];
    }

    private function typeElection($election): string
    {
        $type = strtolower(trim((string) ($election->type ?? '') . ' ' . (string) ($election->type_ref ?? '')));
        $typeNormalise = strtr($type, [
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'à' => 'a', 'â' => 'a',
            'î' => 'i', 'ï' => 'i',
            'ô' => 'o', 'ö' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c',
        ]);

        if (str_contains($typeNormalise, 'commun')) {
            return 'communale';
        }
        if (str_contains($typeNormalise, 'president')) {
            return 'presidentielle';
        }
        return 'legislative';
    }

    private function buildTitre(
        string $niveau,
        int $departementId,
        int $communeId,
        Collection $departements,
        Collection $communes
    ): string {
        if ($niveau === 'departement' && $departementId > 0) {
            $dep = $departements->firstWhere('id', $departementId);
            return $dep ? 'Département de ' . $dep->nom : 'Département';
        }

        if ($niveau === 'commune' && $communeId > 0) {
            $com = $communes->firstWhere('id', $communeId);
            return $com ? 'Commune de ' . $com->nom : 'Commune';
        }

        return 'National';
    }
}

