(function () {
    const socketUrl = window.INSTA_SOCKET_URL || 'ws://localhost:8080';
    const userId = Number(window.INSTA_USER_ID || 0);

    const state = {
        ws: null,
        connected: false,
        authenticated: false,
        queue: [],
        reconnectTimer: null,
        reconnectDelay: 1000,
        pending: new Map(),
    };

    function emit(name, detail) {
        window.dispatchEvent(new CustomEvent(name, { detail }));
    }

    function connect() {
        if (!userId || state.ws?.readyState === WebSocket.OPEN || state.ws?.readyState === WebSocket.CONNECTING) {
            return;
        }

        try {
            state.ws = new WebSocket(socketUrl);
        } catch (error) {
            scheduleReconnect();
            return;
        }

        state.ws.addEventListener('open', () => {
            state.connected = true;
            state.reconnectDelay = 1000;
            rawSend({ type: 'auth' });
            emit('insta:socket-open', {});
        });

        state.ws.addEventListener('message', event => {
            let payload;
            try {
                payload = JSON.parse(event.data);
            } catch (error) {
                return;
            }

            if (payload.type === 'auth_ok') {
                state.authenticated = true;
                flushQueue();
                emit('insta:socket-auth', payload);
                return;
            }

            if (payload.type === 'message') {
                resolvePending(payload.client_request_id, payload);
                emit('insta:socket-message', payload);
                return;
            }

            if (payload.type === 'message_error') {
                rejectPending(payload.client_request_id, payload.message || 'Message failed');
                emit('insta:socket-error', payload);
            }
        });

        state.ws.addEventListener('close', () => {
            state.connected = false;
            state.authenticated = false;
            emit('insta:socket-close', {});
            scheduleReconnect();
        });

        state.ws.addEventListener('error', () => {
            emit('insta:socket-error', { message: 'Socket connection failed' });
        });
    }

    function scheduleReconnect() {
        if (state.reconnectTimer || !userId) return;
        state.reconnectTimer = setTimeout(() => {
            state.reconnectTimer = null;
            state.reconnectDelay = Math.min(state.reconnectDelay * 1.5, 10000);
            connect();
        }, state.reconnectDelay);
    }

    function rawSend(payload) {
        state.ws.send(JSON.stringify(payload));
    }

    function flushQueue() {
        while (state.queue.length && state.authenticated && state.ws?.readyState === WebSocket.OPEN) {
            rawSend(state.queue.shift());
        }
    }

    function send(payload) {
        if (state.authenticated && state.ws?.readyState === WebSocket.OPEN) {
            rawSend(payload);
        } else {
            state.queue.push(payload);
            connect();
        }
    }

    function resolvePending(id, payload) {
        if (!id || !state.pending.has(id)) return;
        state.pending.get(id).resolve(payload);
        state.pending.delete(id);
    }

    function rejectPending(id, message) {
        if (!id || !state.pending.has(id)) return;
        state.pending.get(id).reject(new Error(message));
        state.pending.delete(id);
    }

    function clientRequestId() {
        return 'msg_' + Date.now() + '_' + Math.random().toString(16).slice(2);
    }

    window.InstaSocket = {
        connect,
        isReady() {
            return state.authenticated && state.ws?.readyState === WebSocket.OPEN;
        },
        sendMessage(receiverId, message, sharePostId) {
            const clientId = clientRequestId();
            const payload = {
                type: 'message',
                receiver_id: Number(receiverId || 0),
                message: message || '',
                share_post_id: Number(sharePostId || 0),
                client_request_id: clientId,
            };

            const promise = new Promise((resolve, reject) => {
                state.pending.set(clientId, { resolve, reject });
                setTimeout(() => {
                    rejectPending(clientId, 'Socket response timed out');
                }, 10000);
            });

            send(payload);
            return promise;
        },
    };

    if (userId) connect();
})();
