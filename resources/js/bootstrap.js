import 'bootstrap';

/**
 * We'll load the axios HTTP library which allows us to easily issue requests
 * to our Laravel back-end. This library automatically handles sending the
 * CSRF token as a header based on the value of the "XSRF" token cookie.
 */

import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allows your team to easily build robust real-time web applications.
 */

import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

// Enable pusher logging for easier debugging
Pusher.logToConsole = true;

// Determine if we're in production by checking the URL
const isProduction = window.location.hostname.includes('onrender.com');
const host = isProduction ? 'noteapp-7orp.onrender.com' : window.location.hostname;
const wsProtocol = isProduction ? 'wss' : 'ws';
const httpProtocol = isProduction ? 'https' : 'http';

window.Echo = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY || 'aa6e129c9fdc2f8333c3',
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER || 'ap1',
    wsHost: host,
    wsPort: isProduction ? 443 : 6001,
    wssPort: isProduction ? 443 : 6001,
    forceTLS: isProduction,
    encrypted: true,
    disableStats: true,
    enabledTransports: ['ws', 'wss'],
    auth: {
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    }
});
