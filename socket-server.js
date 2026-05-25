const http = require('http');
const crypto = require('crypto');

const PORT = Number(process.env.SOCKET_PORT || 8080);
const PHP_BASE_PATH = (process.env.PHP_BASE_PATH || 'insta_out').replace(/^\/|\/$/g, '');
const PHP_BASE_URL = (
    process.env.PHP_BASE_URL ||
    `http://localhost/${PHP_BASE_PATH}`
).replace(/\/$/, '');

const clientsByUser = new Map();

function send(ws, payload) {
    if (ws.destroyed || !ws.writable) return;
    const data = Buffer.from(JSON.stringify(payload));
    let header;

    if (data.length < 126) {
        header = Buffer.from([0x81, data.length]);
    } else if (data.length < 65536) {
        header = Buffer.alloc(4);
        header[0] = 0x81;
        header[1] = 126;
        header.writeUInt16BE(data.length, 2);
    } else {
        header = Buffer.alloc(10);
        header[0] = 0x81;
        header[1] = 127;
        header.writeBigUInt64BE(BigInt(data.length), 2);
    }

    ws.write(Buffer.concat([header, data]));
}

function addClient(userId, ws) {
    if (!clientsByUser.has(userId)) clientsByUser.set(userId, new Set());
    clientsByUser.get(userId).add(ws);
    ws.userId = userId;
}

function removeClient(ws) {
    if (!ws.userId || !clientsByUser.has(ws.userId)) return;
    clientsByUser.get(ws.userId).delete(ws);
    if (clientsByUser.get(ws.userId).size === 0) clientsByUser.delete(ws.userId);
}

function broadcastTo(userId, payload) {
    const sockets = clientsByUser.get(Number(userId));
    if (!sockets) return;
    sockets.forEach(client => send(client, payload));
}

function decodeFrames(buffer) {
    const messages = [];
    let offset = 0;

    while (offset + 2 <= buffer.length) {
        const first = buffer[offset++];
        const second = buffer[offset++];
        const opcode = first & 0x0f;
        const masked = (second & 0x80) !== 0;
        let length = second & 0x7f;

        if (length === 126) {
            if (offset + 2 > buffer.length) break;
            length = buffer.readUInt16BE(offset);
            offset += 2;
        } else if (length === 127) {
            if (offset + 8 > buffer.length) break;
            length = Number(buffer.readBigUInt64BE(offset));
            offset += 8;
        }

        let mask;
        if (masked) {
            if (offset + 4 > buffer.length) break;
            mask = buffer.subarray(offset, offset + 4);
            offset += 4;
        }

        if (offset + length > buffer.length) break;
        const payload = Buffer.from(buffer.subarray(offset, offset + length));
        offset += length;

        if (masked) {
            for (let i = 0; i < payload.length; i++) payload[i] ^= mask[i % 4];
        }

        if (opcode === 0x8) {
            messages.push({ type: 'close' });
        } else if (opcode === 0x1) {
            messages.push({ type: 'text', data: payload.toString('utf8') });
        }
    }

    return messages;
}

async function postPhp(path, params, cookie) {
    const response = await fetch(`${PHP_BASE_URL}/${path}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'Cookie': cookie || '',
        },
        body: new URLSearchParams(params).toString(),
    });

    return response.json();
}

async function getPhp(path, cookie) {
    const response = await fetch(`${PHP_BASE_URL}/${path}`, {
        headers: { 'Cookie': cookie || '' },
    });

    return response.json();
}

async function handleMessage(ws, raw) {
    let payload;
    try {
        payload = JSON.parse(raw);
    } catch (error) {
        send(ws, { type: 'error', message: 'Invalid JSON' });
        return;
    }

    if (payload.type === 'auth') {
        try {
            const data = await getPhp('controllers/socket_auth.php', ws.cookie);
            if (!data.success) {
                send(ws, { type: 'auth_error' });
                return;
            }
            addClient(Number(data.user_id), ws);
            send(ws, { type: 'auth_ok', user_id: Number(data.user_id) });
        } catch (error) {
            send(ws, { type: 'auth_error' });
        }
        return;
    }

    if (payload.type === 'message') {
        try {
            const result = await postPhp('controllers/socket_message_api.php', {
                receiver_id: payload.receiver_id || '',
                message: payload.message || '',
                share_post_id: payload.share_post_id || '',
            }, ws.cookie);

            if (!result.success) {
                send(ws, { type: 'message_error', message: result.message || 'Message failed' });
                return;
            }

            const event = {
                type: 'message',
                message: result.message,
                client_request_id: payload.client_request_id || '',
            };
            broadcastTo(result.message.sender_id, event);
            broadcastTo(result.message.receiver_id, event);
        } catch (error) {
            send(ws, { type: 'message_error', message: 'Message failed' });
        }
    }
}

const server = http.createServer((req, res) => {
    res.writeHead(200, { 'Content-Type': 'text/plain' });
    res.end('Instagram clone socket server is running.\n');
});

server.on('upgrade', (req, socket) => {
    const key = req.headers['sec-websocket-key'];
    if (!key) {
        socket.destroy();
        return;
    }

    const accept = crypto
        .createHash('sha1')
        .update(key + '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')
        .digest('base64');

    socket.cookie = req.headers.cookie || '';
    socket.write([
        'HTTP/1.1 101 Switching Protocols',
        'Upgrade: websocket',
        'Connection: Upgrade',
        `Sec-WebSocket-Accept: ${accept}`,
        '',
        '',
    ].join('\r\n'));

    socket.on('data', chunk => {
        decodeFrames(chunk).forEach(frame => {
            if (frame.type === 'close') socket.end();
            if (frame.type === 'text') handleMessage(socket, frame.data);
        });
    });
    socket.on('close', () => removeClient(socket));
    socket.on('error', () => removeClient(socket));
});

server.on('error', error => {
    if (error.code === 'EADDRINUSE') {
        console.error(`Port ${PORT} is already in use. Socket server is probably already running.`);
        console.error(`Use the existing ws://localhost:${PORT} server, or stop the old node process before starting a new one.`);
        process.exit(1);
    }

    throw error;
});

server.listen(PORT, () => {
    console.log(`Socket server running on ws://localhost:${PORT}`);
    console.log(`Using PHP base URL ${PHP_BASE_URL}`);
});
