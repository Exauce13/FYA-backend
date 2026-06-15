import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

const token = () => window.FYARealtimeToken
    ?? localStorage.getItem('auth_token')
    ?? localStorage.getItem('token')
    ?? localStorage.getItem('sanctum_token')
    ?? '';

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
    authEndpoint: '/broadcasting/auth',
    authorizer: (channel) => ({
        authorize: (socketId, callback) => {
            fetch('/broadcasting/auth', {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(token() ? { 'Authorization': `Bearer ${token()}` } : {}),
                    'X-Socket-ID': socketId,
                },
                body: JSON.stringify({
                    socket_id: socketId,
                    channel_name: channel.name,
                }),
            })
                .then((response) => response.json())
                .then((data) => callback(null, data))
                .catch((error) => callback(error));
        },
    }),
});
