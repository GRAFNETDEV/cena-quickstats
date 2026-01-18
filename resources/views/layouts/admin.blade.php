<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'CENA QuickStats') }} - @yield('title', 'Dashboard')</title>
    
    <!-- Tailwind CSS -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        
        body {
            font-family: 'Inter', sans-serif;
        }
        
        .sidebar {
            transition: transform 0.3s ease-in-out;
        }
        
        @media (max-width: 768px) {
            .sidebar.collapsed {
                transform: translateX(-100%);
            }
        }
        
        .menu-item {
            transition: all 0.2s ease;
        }
        
        .menu-item:hover {
  background-color: rgba(0, 135, 81, 0.18); /* benin green */
  border-left: 4px solid #FCD116;          /* benin yellow */
}

.menu-item.active {
  background-color: rgba(0, 135, 81, 0.26);
  border-left: 4px solid #FCD116;
  font-weight: 700;
}
        
        .submenu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }
        
        .submenu.open {
            max-height: 500px;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            .sidebar {
                display: none !important;
            }
        }
    </style>
    
    @stack('styles')
</head>
<body class="bg-gray-50" x-data="{ sidebarOpen: true }">
    
    <div class="flex h-screen overflow-hidden">
        
        <!-- Sidebar -->
        <aside class="sidebar bg-gradient-to-b from-benin-green-800 via-benin-green-700 to-benin-green-900 text-white w-64 flex-shrink-0 no-print"
       :class="{ 'collapsed': !sidebarOpen }"
       x-show="sidebarOpen"
       x-transition>

            
            <!-- Logo -->
            <div class="p-6 border-b border-blue-700">
                <div class="flex items-center space-x-3">
                    <div class="bg-white rounded-lg p-2">
                        <i class="fas fa-vote-yea text-blue-900 text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold">CENA</h1>
                        <p class="text-xs text-blue-200">QuickStats</p>
                    </div>
                </div>
            </div>
            
            <!-- Menu -->
            <nav class="flex-1 overflow-y-auto p-4 space-y-2" x-data="{ 
                openSubmenu: '{{ request()->is('stats/*') ? 'stats' : (request()->is('resultats/*') ? 'resultats' : '') }}'
            }">
                
                <!-- Dashboard -->
                <a href="{{ route('dashboard') }}" 
                   class="menu-item flex items-center space-x-3 px-4 py-3 rounded-lg {{ request()->is('dashboard') ? 'active' : '' }}">
                    <i class="fas fa-home w-5"></i>
                    <span>Dashboard</span>
                </a>
                
                <!-- Statistiques -->
                <div>
                    <button @click="openSubmenu = openSubmenu === 'stats' ? '' : 'stats'"
                            class="menu-item w-full flex items-center justify-between px-4 py-3 rounded-lg {{ request()->is('stats/*') ? 'active' : '' }}">
                        <div class="flex items-center space-x-3">
                            <i class="fas fa-chart-bar w-5"></i>
                            <span>Statistiques</span>
                        </div>
                        <i class="fas fa-chevron-down text-xs transition-transform" 
                           :class="{ 'rotate-180': openSubmenu === 'stats' }"></i>
                    </button>
                    
                    <div class="submenu ml-4 mt-1 space-y-1" :class="{ 'open': openSubmenu === 'stats' }">
                        <a href="{{ route('stats.national') }}" 
                           class="flex items-center space-x-2 px-4 py-2 rounded text-sm hover:bg-benin-green-700/40 {{ request()->is('stats/national') ? 'bg-benin-green-700/50' : '' }}">
                            <i class="fas fa-flag w-4"></i>
                            <span>National</span>
                        </a>
                        <a href="{{ route('stats.departement') }}" 
                           class="flex items-center space-x-2 px-4 py-2 rounded text-sm hover:bg-benin-green-700/40 {{ request()->is('stats/departement') ? 'bg-benin-green-700/50' : '' }}">
                            <i class="fas fa-map-marked-alt w-4"></i>
                            <span>Départements</span>
                        </a>
                        <a href="{{ route('stats.circonscription') }}" 
                           class="flex items-center space-x-2 px-4 py-2 rounded text-sm hover:bg-benin-green-700/40 {{ request()->is('stats/circonscription') ? 'bg-benin-green-700/50' : '' }}">
                            <i class="fas fa-map-marker-alt w-4"></i>
                            <span>Circonscriptions</span>
                        </a>
                        <a href="{{ route('stats.commune') }}" 
                           class="flex items-center space-x-2 px-4 py-2 rounded text-sm hover:bg-benin-green-700/40 {{ request()->is('stats/commune') ? 'bg-benin-green-700/50' : '' }}">
                            <i class="fas fa-city w-4"></i>
                            <span>Communes</span>
                        </a>
                        <a href="{{ route('stats.arrondissement') }}" 
                           class="flex items-center space-x-2 px-4 py-2 rounded text-sm hover:bg-benin-green-700/40 {{ request()->is('stats/arrondissement') ? 'bg-benin-green-700/50' : '' }}">
                            <i class="fas fa-building w-4"></i>
                            <span>Arrondissements</span>
                        </a>
                        <a href="{{ route('stats.village') }}" 
                           class="flex items-center space-x-2 px-4 py-2 rounded text-sm hover:bg-benin-green-700/40{{ request()->is('stats/village') ? 'bg-benin-green-700/50' : '' }}">
                            <i class="fas fa-home-lg-alt w-4"></i>
                            <span>Villages/Quartiers</span>
                        </a>
                    </div>
                </div>
                
                <!-- Résultats -->
                <div>
                    <button @click="openSubmenu = openSubmenu === 'resultats' ? '' : 'resultats'"
                            class="menu-item w-full flex items-center justify-between px-4 py-3 rounded-lg {{ request()->is('resultats*') ? 'active' : '' }}">
                        <div class="flex items-center space-x-3">
                            <i class="fas fa-poll w-5"></i>
                            <span>Résultats</span>
                        </div>
                        <i class="fas fa-chevron-down text-xs transition-transform" 
                           :class="{ 'rotate-180': openSubmenu === 'resultats' }"></i>
                    </button>
                    
                    <div class="submenu ml-4 mt-1 space-y-1" :class="{ 'open': openSubmenu === 'resultats' }">
                        <a href="{{ route('resultats') }}" 
                           class="flex items-center space-x-2 px-4 py-2 rounded text-sm hover:bg-benin-green-700/40">
                            <i class="fas fa-users w-4"></i>
                            <span>Par entité politique</span>
                        </a>
                    </div>
                </div>
                
                <!-- Rapports -->
                <a href="#" 
                   class="menu-item flex items-center space-x-3 px-4 py-3 rounded-lg">
                    <i class="fas fa-file-alt w-5"></i>
                    <span>Rapports</span>
                </a>
                
                <!-- Paramètres -->
                <a href="{{ route('parametres.index') }}" 
   class="menu-item flex items-center space-x-3 px-4 py-3 rounded-lg {{ request()->is('parametres*') ? 'active' : '' }}">
    <i class="fas fa-cog w-5"></i>
    <span>Paramètres</span>
</a>
                
            </nav>
            
            <!-- User Info -->
            <div class="p-4 border-t border-blue-700">
                <div class="flex items-center space-x-3">
                    <div class="bg-blue-700 rounded-full w-10 h-10 flex items-center justify-center">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-semibold">{{ Auth::user()->nom_complet }}</p>
                        <p class="text-xs text-blue-200">{{ Auth::user()->email }}</p>
                    </div>
                </div>
            </div>
            
        </aside>
        
        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            
            <!-- Header -->
            <header class="bg-white shadow-sm z-10 no-print">
                <div class="flex items-center justify-between px-6 py-4">
                    
                    <div class="flex items-center space-x-4">
                        <!-- Mobile Menu Toggle -->
                        <button @click="sidebarOpen = !sidebarOpen" 
                                class="text-gray-600 hover:text-gray-900 lg:hidden">
                            <i class="fas fa-bars text-xl"></i>
                        </button>
                        
                        <!-- Breadcrumb -->
                        <nav class="hidden md:flex items-center space-x-2 text-sm">
                            @yield('breadcrumb')
                        </nav>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        
                        <!-- Election Selector -->
                        @if(isset($elections) && $elections->count() > 0)
                        <form method="POST" action="{{ route('select.election') }}" class="hidden md:block">
                            @csrf
                            <select name="election_id" 
                                    onchange="this.form.submit()"
                                    class="rounded-lg border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500">
                                @foreach($elections as $election)
                                    <option value="{{ $election->id }}" 
                                            @if(session('election_active') == $election->id) selected @endif>
                                        {{ $election->nom }}
                                    </option>
                                @endforeach
                            </select>
                        </form>
                        @endif
                        
                        <!-- User Menu -->
                        <div x-data="{ open: false }" class="relative">
                            <button @click="open = !open" 
                                    class="flex items-center space-x-2 text-gray-700 hover:text-gray-900">
                                <div class="bg-blue-600 text-white rounded-full w-8 h-8 flex items-center justify-center">
                                    <span class="text-sm font-semibold">
                                        {{ substr(Auth::user()->nom, 0, 1) }}{{ substr(Auth::user()->prenom, 0, 1) }}
                                    </span>
                                </div>
                                <i class="fas fa-chevron-down text-xs"></i>
                            </button>
                            
                            <div x-show="open" 
                                 @click.away="open = false"
                                 x-transition
                                 class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg py-2 z-50">
                                <a href="{{ route('profile.edit') }}" 
                                   class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-user-circle mr-2"></i> Mon profil
                                </a>
                                <hr class="my-2">
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" 
                                            class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-100">
                                        <i class="fas fa-sign-out-alt mr-2"></i> Déconnexion
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                    </div>
                </div>
            </header>
            
            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto bg-gray-50 p-6">
                @yield('content')
            </main>
            
            <!-- Footer -->
            <footer class="bg-white border-t border-gray-200 px-6 py-4 no-print">
                <div class="flex flex-col md:flex-row items-center justify-between text-sm text-gray-600">
                    <div>
                        © {{ date('Y') }} <strong>CENA</strong> - Commission Électorale Nationale Autonome du Bénin
                    </div>
                    <div class="flex items-center space-x-4 mt-2 md:mt-0">
                        <span>Version 1.0.0</span>
                        <span>•</span>
                        <a href="#" class="hover:text-blue-600">Documentation</a>
                        <span>•</span>
                        <a href="#" class="hover:text-blue-600">Support</a>
                    </div>
                </div>
            </footer>
            
        </div>
    </div>
    
    <!-- Toast Notifications -->
    <div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2">
        @if(session('success'))
        <div class="bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg flex items-center space-x-3">
            <i class="fas fa-check-circle"></i>
            <span>{{ session('success') }}</span>
        </div>
        @endif
        
        @if(session('error'))
        <div class="bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg flex items-center space-x-3">
            <i class="fas fa-exclamation-circle"></i>
            <span>{{ session('error') }}</span>
        </div>
        @endif
    </div>

        <!-- Modales Personnalisées (Juste avant @stack('scripts')) -->
@include('components.modals')
    
    @stack('scripts')
    
    <script>
        // Auto-hide toast notifications
        setTimeout(() => {
            const toasts = document.querySelectorAll('#toast-container > div');
            toasts.forEach(toast => {
                toast.style.transition = 'opacity 0.5s';
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 500);
            });
        }, 3000);
    </script>
    
</body>
</html>