@extends('layouts.admin')

@section('title', 'Dashboard')

@section('breadcrumb')
    <span class="text-gray-900 font-semibold">Dashboard</span>
@endsection

@section('content')
<div class="space-y-6 animate-fade-in">

    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-benin-green-50 text-benin-green-800 border border-benin-green-200 text-xs font-semibold">
                <span class="w-2 h-2 rounded-full bg-benin-green-500"></span>
                CENA QuickStats — Local
            </div>

            <h1 class="text-3xl font-bold text-gray-900 mt-3">Tableau de bord</h1>
            <p class="text-gray-600 mt-1">Vue d'ensemble des statistiques et accès rapides.</p>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('stats.national') }}"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-benin-green-600 text-white font-semibold hover:bg-benin-green-700 shadow-sm">
                <i class="fas fa-chart-pie"></i>
                <span>Stats Nationales</span>
            </a>
            <a href="{{ route('resultats') }}"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-900 text-white font-semibold hover:bg-black shadow-sm">
                <i class="fas fa-poll-h"></i>
                <span>Résultats</span>
            </a>
        </div>
    </div>

    <!-- Election Active -->
    @if($electionActive)
    <div class="rounded-2xl shadow-lg overflow-hidden border border-benin-green-100">
        <div class="bg-gradient-benin p-1">
            <div class="bg-white/90 backdrop-blur rounded-2xl p-6 md:p-8">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                    <div>
                        <div class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                            <i class="fas fa-check-circle text-benin-green-600"></i>
                            Élection active
                        </div>
                        <h2 class="text-2xl font-bold text-gray-900 mt-2">{{ $electionActive->nom }}</h2>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-4 text-sm">
                            <div class="flex items-center gap-2 text-gray-700">
                                <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-benin-yellow-100 text-benin-yellow-800">
                                    <i class="fas fa-calendar-alt"></i>
                                </span>
                                <div>
                                    <div class="text-gray-500">Date du scrutin</div>
                                    <div class="font-semibold">{{ $electionActive->date_scrutin->format('d/m/Y') }}</div>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 text-gray-700">
                                <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-benin-green-100 text-benin-green-800">
                                    <i class="fas fa-vote-yea"></i>
                                </span>
                                <div>
                                    <div class="text-gray-500">Type</div>
                                    <div class="font-semibold">{{ $electionActive->typeElection->nom }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="md:text-right">
                        <div class="inline-flex flex-col items-start md:items-end gap-1 px-5 py-3 rounded-2xl bg-white shadow-sm border border-gray-100">
                            <div class="text-xs text-gray-500 font-semibold uppercase">Statut</div>
                            <div class="text-2xl font-extrabold text-gray-900">{{ ucfirst($electionActive->statut) }}</div>
                            <div class="text-xs text-gray-500">Dernière mise à jour : {{ now()->format('d/m/Y H:i') }}</div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex flex-wrap gap-2">
                    <a href="{{ route('stats.departement') }}"
                       class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-benin-green-600 text-white font-semibold hover:bg-benin-green-700">
                        <i class="fas fa-map-marked-alt"></i>
                        Départements
                    </a>
                    <a href="{{ route('stats.circonscription') }}"
                       class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-benin-yellow-500 text-gray-900 font-semibold hover:bg-benin-yellow-600">
                        <i class="fas fa-map-marker-alt"></i>
                        Circonscriptions
                    </a>
                    <a href="{{ route('stats.commune') }}"
                       class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-benin-red-600 text-white font-semibold hover:bg-benin-red-700">
                        <i class="fas fa-city"></i>
                        Communes
                    </a>
                </div>

            </div>
        </div>
    </div>
    @else
    <div class="bg-benin-red-50 border border-benin-red-200 rounded-xl p-5">
        <div class="flex items-start gap-3">
            <i class="fas fa-exclamation-triangle text-benin-red-600 mt-1"></i>
            <div>
                <div class="font-semibold text-benin-red-900">Aucune élection active</div>
                <div class="text-sm text-benin-red-800 mt-1">
                    Active une élection pour accéder aux statistiques et résultats.
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Quick Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">

        <div class="bg-white rounded-2xl shadow-sm p-6 border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Total Élections</p>
                    <p class="text-3xl font-extrabold text-gray-900 mt-2">{{ $elections->count() }}</p>
                </div>
                <div class="bg-benin-green-50 rounded-2xl p-4 border border-benin-green-100">
                    <i class="fas fa-vote-yea text-benin-green-600 text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm p-6 border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Départements</p>
                    <p class="text-3xl font-extrabold text-gray-900 mt-2">12</p>
                </div>
                <div class="bg-benin-yellow-50 rounded-2xl p-4 border border-benin-yellow-100">
                    <i class="fas fa-map-marked-alt text-benin-yellow-700 text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm p-6 border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Circonscriptions</p>
                    <p class="text-3xl font-extrabold text-gray-900 mt-2">24</p>
                </div>
                <div class="bg-benin-green-50 rounded-2xl p-4 border border-benin-green-100">
                    <i class="fas fa-map-marker-alt text-benin-green-700 text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm p-6 border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Communes</p>
                    <p class="text-3xl font-extrabold text-gray-900 mt-2">77</p>
                </div>
                <div class="bg-benin-red-50 rounded-2xl p-4 border border-benin-red-100">
                    <i class="fas fa-city text-benin-red-600 text-2xl"></i>
                </div>
            </div>
        </div>

    </div>

    <!-- Navigation Grid -->
    <div>
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-gray-900">Accès rapide aux statistiques</h2>
            <div class="text-sm text-gray-500">Choisis un niveau pour explorer</div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mt-4">

            @php
                $cards = [
                    [
                        'route' => route('stats.national'),
                        'title' => 'National',
                        'desc'  => "Vue d'ensemble complète",
                        'icon'  => 'fa-flag',
                        'bg'    => 'bg-gradient-benin',
                    ],
                    [
                        'route' => route('stats.departement'),
                        'title' => 'Départements',
                        'desc'  => 'Statistiques par département',
                        'icon'  => 'fa-map-marked-alt',
                        'bg'    => 'bg-gradient-green',
                    ],
                    [
                        'route' => route('stats.circonscription'),
                        'title' => 'Circonscriptions',
                        'desc'  => 'Résultats législatifs',
                        'icon'  => 'fa-map-marker-alt',
                        'bg'    => 'bg-gradient-to-br from-purple-500 to-purple-600',
                    ],
                    [
                        'route' => route('stats.commune'),
                        'title' => 'Communes',
                        'desc'  => 'Niveau communal',
                        'icon'  => 'fa-city',
                        'bg'    => 'bg-gradient-to-br from-benin-yellow-500 to-benin-yellow-600',
                    ],
                    [
                        'route' => route('stats.arrondissement'),
                        'title' => 'Arrondissements',
                        'desc'  => "Détails par arrondissement",
                        'icon'  => 'fa-building',
                        'bg'    => 'bg-gradient-to-br from-benin-red-500 to-benin-red-600',
                    ],
                    [
                        'route' => route('resultats'),
                        'title' => 'Résultats',
                        'desc'  => 'Par entité politique',
                        'icon'  => 'fa-poll-h',
                        'bg'    => 'bg-gradient-to-br from-gray-900 to-gray-700',
                    ],
                ];
            @endphp

            @foreach($cards as $c)
            <a href="{{ $c['route'] }}"
               class="group rounded-2xl shadow-lg p-6 text-white hover:shadow-xl transition-all transform hover:-translate-y-1 {{ $c['bg'] }}">
                <div class="flex items-center justify-between mb-4">
                    <div class="bg-white/20 rounded-2xl p-3">
                        <i class="fas {{ $c['icon'] }} text-2xl"></i>
                    </div>
                    <i class="fas fa-arrow-right text-xl group-hover:translate-x-2 transition-transform"></i>
                </div>
                <h3 class="text-xl font-extrabold mb-1">{{ $c['title'] }}</h3>
                <p class="text-white/90 text-sm">{{ $c['desc'] }}</p>
            </a>
            @endforeach

        </div>
    </div>

    <!-- Recent Activity -->
    <div class="bg-white rounded-2xl shadow-sm p-6 border border-gray-100">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-semibold text-gray-900">
                <i class="fas fa-clock text-benin-green-600 mr-2"></i>
                Activités récentes
            </h2>
            <span class="text-xs text-gray-400">Démo (à brancher plus tard)</span>
        </div>

        <div class="space-y-3">
            <div class="flex items-center gap-3 text-sm">
                <div class="bg-benin-green-50 text-benin-green-700 rounded-2xl p-2 border border-benin-green-100">
                    <i class="fas fa-check"></i>
                </div>
                <div class="flex-1">
                    <p class="text-gray-900">PV validé pour <strong>Cotonou</strong></p>
                    <p class="text-gray-500 text-xs">Il y a 5 minutes</p>
                </div>
            </div>

            <div class="flex items-center gap-3 text-sm">
                <div class="bg-benin-yellow-50 text-benin-yellow-800 rounded-2xl p-2 border border-benin-yellow-100">
                    <i class="fas fa-upload"></i>
                </div>
                <div class="flex-1">
                    <p class="text-gray-900">Nouvelle saisie pour <strong>Porto-Novo</strong></p>
                    <p class="text-gray-500 text-xs">Il y a 12 minutes</p>
                </div>
            </div>

            <div class="flex items-center gap-3 text-sm">
                <div class="bg-benin-red-50 text-benin-red-700 rounded-2xl p-2 border border-benin-red-100">
                    <i class="fas fa-edit"></i>
                </div>
                <div class="flex-1">
                    <p class="text-gray-900">PV modifié pour <strong>Parakou</strong></p>
                    <p class="text-gray-500 text-xs">Il y a 30 minutes</p>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
