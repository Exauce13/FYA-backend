import './echo';

const apiToken = () => window.FYARealtimeToken
    ?? localStorage.getItem('auth_token')
    ?? localStorage.getItem('token')
    ?? localStorage.getItem('sanctum_token')
    ?? '';

const apiHeaders = () => ({
    'Accept': 'application/json',
    'Content-Type': 'application/json',
    ...(apiToken() ? { 'Authorization': `Bearer ${apiToken()}` } : {}),
});

const heartbeat = {
    timer: null,
    start(intervalMs = 60000) {
        this.stop();
        markOnline();
        this.timer = window.setInterval(markOnline, intervalMs);
    },
    stop() {
        if (this.timer) {
            window.clearInterval(this.timer);
            this.timer = null;
        }
    },
};

function markOnline() {
    return fetch('/api/presence/online', {
        method: 'POST',
        credentials: 'include',
        headers: apiHeaders(),
    }).catch(() => {});
}

function markOffline() {
    heartbeat.stop();

    return fetch('/api/presence/offline', {
        method: 'POST',
        credentials: 'include',
        headers: apiHeaders(),
    }).catch(() => {});
}

function setAuthToken(token) {
    window.FYARealtimeToken = token;
}

function listenNotifications(userId, callback) {
    return window.Echo.private(`App.Models.User.${userId}`)
        .listen('.notification.created', callback)
        .listen('.candidature.status.updated', callback);
}

function listenConversation(conversationId, callback) {
    return window.Echo.private(`chat.${conversationId}`)
        .listen('.message.sent', callback);
}

function listenMetierAppelsOffres(metierId, callback) {
    return window.Echo.private(`metier.${metierId}`)
        .listen('.appel-offre.created', callback);
}

function listenAppelOffreCandidatures(appelOffreId, callback) {
    return window.Echo.private(`appel-offre.${appelOffreId}`)
        .listen('.candidature.created', callback)
        .listen('.candidature.status.updated', callback);
}

function listenPostLikes(postId, callback) {
    return window.Echo.channel(`post.${postId}`)
        .listen('.post.like.updated', callback);
}

function leave(channel) {
    window.Echo.leave(channel);
}

window.FYARealtime = {
    heartbeat,
    setAuthToken,
    markOnline,
    markOffline,
    listenNotifications,
    listenConversation,
    listenMetierAppelsOffres,
    listenAppelOffreCandidatures,
    listenPostLikes,
    leave,
};
