@php
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
@endphp

<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Web App Manifest -->
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <meta name="theme-color" content="#3490dc">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <meta name="apple-mobile-web-app-title" content="Notes App">
    <link rel="apple-touch-icon" href="{{ asset('icons/icon-152x152.png') }}">

    <!-- Fonts -->
    <link rel="dns-prefetch" href="//fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=Nunito" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}?v={{ time() }}">

    <!-- Scripts -->
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
</head>
<body class="{{ auth()->check() && isset(auth()->user()->preferences['font_size']) ? auth()->user()->preferences['font_size'] . '-font' : 'medium-font' }} {{ auth()->check() && isset(auth()->user()->preferences['theme']) && auth()->user()->preferences['theme'] === 'dark' ? 'dark-theme' : '' }}">
    <div id="app">
        <nav class="navbar navbar-expand-md navbar-light bg-white shadow-sm">
            <div class="container">
                <a class="navbar-brand" href="{{ route('notes.index') }}">
                    {{ config('app.name', 'Notes App') }}
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="{{ __('Toggle navigation') }}">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <!-- Left Side Of Navbar -->
                    <ul class="navbar-nav me-auto">
                        @auth
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('notes.index') ? 'active' : '' }}" href="{{ route('notes.index') }}">
                                    <i class="fas fa-sticky-note me-1"></i> My Notes
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('notes.shares.index') ? 'active' : '' }}" href="{{ route('notes.shares.index') }}">
                                    <i class="fas fa-share-alt me-1"></i> Shared with Me
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('labels.index') ? 'active' : '' }}" href="{{ route('labels.index') }}">
                                    <i class="fas fa-tags me-1"></i> Manage Labels
                                </a>
                            </li>
                            
                            <!-- Offline status indicator -->
                            <li class="nav-item">
                                <div id="offline-indicator" class="d-none">
                                    <span class="badge bg-warning text-dark">
                                        <i class="fas fa-wifi-slash me-1"></i> Offline Mode
                                    </span>
                                </div>
                            </li>
                        @endauth
                    </ul>

                    <!-- Right Side Of Navbar -->
                    <ul class="navbar-nav ms-auto">
                        <!-- Authentication Links -->
                        @guest
                            @if (Route::has('login'))
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('login') ? 'fw-bold' : '' }}" href="{{ route('login') }}">{{ __('Login') }}</a>
                                </li>
                            @endif

                            @if (Route::has('register'))
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('register') ? 'fw-bold' : '' }}" href="{{ route('register') }}">{{ __('Register') }}</a>
                                </li>
                            @endif
                        @else
                            <!-- Sync status indicator -->
                            <li class="nav-item me-2">
                                <button id="sync-button" class="btn btn-outline-primary btn-sm mt-1 d-none" onclick="window.offlineManager?.syncData()">
                                    <i class="fas fa-sync-alt me-1"></i> Sync
                                    <span id="sync-badge" class="badge rounded-pill bg-danger d-none">0</span>
                                </button>
                            </li>
                            
                            <!-- Notifications Dropdown -->
                            <li class="nav-item dropdown">
                                @php
                                    $unreadNotifications = Auth::user()->unreadNotifications;
                                    $notificationCount = $unreadNotifications->count();
                                @endphp
                                <a id="notificationsDropdown" class="nav-link" href="#" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <i class="fas fa-bell"></i>
                                    <span id="notification-badge" class="badge rounded-pill bg-danger {{ $notificationCount > 0 ? '' : 'd-none' }}">
                                        {{ $notificationCount }}
                                    </span>
                                </a>

                                <div class="dropdown-menu dropdown-menu-end notification-dropdown" aria-labelledby="notificationsDropdown">
                                    <h6 class="dropdown-header">Notifications</h6>
                                    
                                    <div id="notifications-container">
                                    @if($notificationCount > 0)
                                        @foreach($unreadNotifications as $notification)
                                            <div class="dropdown-item notification-item">
                                                <div class="d-flex align-items-start">
                                                    @if($notification->type === 'App\Notifications\NoteSharedNotification')
                                                        <span class="notification-icon bg-primary text-white">
                                                            <i class="fas fa-share-alt"></i>
                                                        </span>
                                                        <div class="ms-2">
                                                            <p class="mb-1">{{ $notification->data['message'] }}</p>
                                                            <div class="d-flex justify-content-between align-items-center">
                                                                <small class="text-muted">{{ $notification->created_at->diffForHumans() }}</small>
                                                                <div>
                                                                    <form method="POST" action="{{ route('notifications.markAsRead', $notification->id) }}" class="d-inline">
                                                                        @csrf
                                                                        <button type="submit" class="btn btn-sm btn-outline-secondary">Mark as Read</button>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @else
                                                        <div>{{ $notification->data['message'] ?? 'New notification' }}</div>
                                                    @endif
                                                </div>
                                            </div>
                                            @if(!$loop->last)
                                                <div class="dropdown-divider"></div>
                                            @endif
                                        @endforeach
                                        
                                        <div class="dropdown-divider"></div>
                                        <form method="POST" action="{{ route('notifications.markAllAsRead') }}" class="d-inline text-center w-100">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-primary w-75 my-2">Mark All as Read</button>
                                        </form>
                                    @else
                                        <div class="dropdown-item text-center py-3">
                                            <span class="text-muted">No new notifications</span>
                                        </div>
                                    @endif
                                    </div>
                                </div>
                            </li>

                            <li class="nav-item dropdown">
                                <a id="navbarDropdown" class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" v-pre>
                                    {{ Auth::user()->name }}
                                </a>

                                <div class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                    <a class="dropdown-item" href="{{ route('notes.index') }}">
                                        {{ __('My Notes') }}
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    
                                    <!-- User Settings Menu -->
                                    <h6 class="dropdown-header">User Settings</h6>
                                    <a class="dropdown-item" href="{{ route('user.profile') }}">
                                        <i class="fas fa-user me-2"></i>{{ __('View Profile') }}
                                    </a>
                                    <a class="dropdown-item" href="{{ route('user.edit-profile') }}">
                                        <i class="fas fa-user-edit me-2"></i>{{ __('Edit Profile') }}
                                    </a>
                                    <a class="dropdown-item" href="{{ route('user.edit-avatar') }}">
                                        <i class="fas fa-image me-2"></i>{{ __('Change Avatar') }}
                                    </a>
                                    <a class="dropdown-item" href="{{ route('user.edit-password') }}">
                                        <i class="fas fa-key me-2"></i>{{ __('Change Password') }}
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    
                                    <a class="dropdown-item" href="{{ route('preferences.edit') }}">
                                        {{ __('Preferences') }}
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item" href="{{ route('logout') }}"
                                       onclick="event.preventDefault();
                                                     document.getElementById('logout-form').submit();">
                                        {{ __('Logout') }}
                                    </a>

                                    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                                        @csrf
                                    </form>
                                </div>
                            </li>
                        @endguest
                    </ul>
                </div>
            </div>
        </nav>

        <main class="py-4">
            @if (auth()->user() && !auth()->user()->hasVerifiedEmail())
                <div class="container mb-4">
                    <div class="alert alert-warning" role="alert">
                        <strong>Your account is not verified!</strong> Please check your email for a verification link.
                        
                        @if (session('resent'))
                            <div class="alert alert-success mt-2" role="alert">
                                A fresh verification link has been sent to your email address.
                            </div>
                        @endif
                        
                        If you did not receive the email, 
                        <form class="d-inline" method="POST" action="{{ route('verification.resend') }}">
                            @csrf
                            <button type="submit" class="btn btn-link p-0 m-0 align-baseline">click here to request another</button>.
                        </form>
                    </div>
                </div>
            @endif
            @yield('content')
        </main>
    </div>
    @stack('scripts')
    <script src="{{ asset('js/refresh-cache.js') }}?v={{ time() }}"></script>
    <script src="{{ asset('js/fix-list-view.js') }}?v={{ time() }}"></script>
    <script src="{{ asset('js/offline-manager.js') }}?v={{ time() }}"></script>
    
    @auth
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Set up notification listener if Echo is available
        if (window.Echo) {
            // Check if we're on the production domain
            const isProduction = window.location.hostname.includes('onrender.com');
            console.log('Setting up Echo on:', isProduction ? 'Production' : 'Development', 'environment');
            
            try {
                // Listen for notifications on the user's private channel
                window.Echo.private('App.Models.User.{{ Auth::id() }}')
                    .notification((notification) => {
                        console.log('New notification received:', notification);
                        
                        // Update notification badge
                        updateNotificationBadge(1);
                        
                        // Add notification to the container
                        if (notification.type === 'App\\Notifications\\NoteSharedNotification') {
                            addNotificationToContainer(notification);
                        }
                    });
                    
                console.log('Notification listener set up for user {{ Auth::id() }}');
            } catch (error) {
                console.error('Error setting up Echo listener:', error);
            }
        } else {
            console.error('Echo is not defined. Real-time notifications will not work.');
        }
        
        // Function to update the notification badge
        function updateNotificationBadge(count) {
            const badge = document.getElementById('notification-badge');
            let currentCount = parseInt(badge.textContent.trim()) || 0;
            
            if (count > 0) {
                // Increment the count
                currentCount += count;
                badge.textContent = currentCount;
                badge.classList.remove('d-none');
            }
        }
        
        // Function to add a notification to the container
        function addNotificationToContainer(notification) {
            const container = document.getElementById('notifications-container');
            const emptyNotice = container.querySelector('.dropdown-item.text-center');
            
            if (emptyNotice) {
                // Remove the "No new notifications" message
                emptyNotice.remove();
            }
            
            // Fix the URL - replace the hostname and port with the current window.location
            const baseUrl = window.location.origin;
            let noteUrl = notification.url;
            // If it's already a relative URL, add the origin
            if (noteUrl.startsWith('/')) {
                noteUrl = baseUrl + noteUrl;
            }
            
            // Create the notification HTML
            const notificationHtml = `
                <div class="dropdown-item notification-item new-notification">
                    <div class="d-flex align-items-start">
                        <span class="notification-icon bg-primary text-white">
                            <i class="fas fa-share-alt"></i>
                        </span>
                        <div class="ms-2">
                            <p class="mb-1">${notification.message}</p>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">Just now</small>
                                <div>
                                    <form method="POST" action="${baseUrl}/notifications/${notification.id}/mark-as-read" class="d-inline">
                                        <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">Mark as Read</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="dropdown-divider"></div>
            `;
            
            // Add the notification to the container
            if (container.firstChild) {
                container.insertAdjacentHTML('afterbegin', notificationHtml);
            } else {
                container.innerHTML = notificationHtml;
                
                // Add the Mark All as Read button if this is the first notification
                container.insertAdjacentHTML('beforeend', `
                    <form method="POST" action="{{ route('notifications.markAllAsRead') }}" class="d-inline text-center w-100">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-primary w-75 my-2">Mark All as Read</button>
                    </form>
                `);
            }
            
            // Flash the notification badge to attract attention
            const badge = document.getElementById('notification-badge');
            badge.classList.add('flash-badge');
            setTimeout(() => {
                badge.classList.remove('flash-badge');
            }, 2000);
        }
        
        // Show sync button if we have an offline manager
        if (window.offlineManager) {
            const syncButton = document.getElementById('sync-button');
            if (syncButton) {
                syncButton.classList.remove('d-none');
            }
        }
    });
    </script>
    
    <style>
    .new-notification {
        animation: highlightNew 2s ease-in-out;
    }
    
    @keyframes highlightNew {
        0% { background-color: rgba(13, 110, 253, 0.2); }
        100% { background-color: transparent; }
    }
    
    .flash-badge {
        animation: flashBadge 1s ease-in-out 2;
    }
    
    @keyframes flashBadge {
        0% { opacity: 1; }
        50% { opacity: 0.2; }
        100% { opacity: 1; }
    }
    
    #offline-indicator {
        margin-top: 8px;
        margin-left: 10px;
    }
    </style>
    @endauth
</body>
</html>
