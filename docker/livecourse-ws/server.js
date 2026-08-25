'use strict';

const crypto = require('crypto');
const http = require('http');
const {createClient} = require('redis');
const {WebSocketServer} = require('ws');

const port = Number(process.env.PORT || 8081);
const secret = process.env.LIVECOURSE_WS_SECRET || '';
const redisUrl = process.env.REDIS_URL || 'redis://valkey:6379';
const redisPassword = process.env.VALKEY_PASSWORD || undefined;

if (secret.length < 32) {
    throw new Error('LIVECOURSE_WS_SECRET must contain at least 32 characters');
}

const decodeBase64Url = value => Buffer.from(value, 'base64url').toString('utf8');

const verifyToken = token => {
    const [payloadPart, signaturePart] = String(token || '').split('.');
    if (!payloadPart || !signaturePart) {
        return null;
    }
    const expected = crypto.createHmac('sha256', secret).update(payloadPart).digest();
    let received;
    try {
        received = Buffer.from(signaturePart, 'base64url');
    } catch (error) {
        return null;
    }
    if (expected.length !== received.length || !crypto.timingSafeEqual(expected, received)) {
        return null;
    }
    try {
        const payload = JSON.parse(decodeBase64Url(payloadPart));
        if (!Number.isInteger(payload.uid) || !Number.isInteger(payload.cmid) ||
                !Number.isInteger(payload.exp) || payload.exp < Math.floor(Date.now() / 1000)) {
            return null;
        }
        return payload;
    } catch (error) {
        return null;
    }
};

const server = http.createServer((request, response) => {
    if (request.url === '/health') {
        response.writeHead(200, {'Content-Type': 'text/plain'});
        response.end('ok\n');
        return;
    }
    response.writeHead(404);
    response.end();
});

const websocketServer = new WebSocketServer({noServer: true});
const rooms = new Map();

server.on('upgrade', (request, socket, head) => {
    const url = new URL(request.url, 'http://localhost');
    const payload = verifyToken(url.searchParams.get('token'));
    if (!payload) {
        socket.write('HTTP/1.1 401 Unauthorized\r\n\r\n');
        socket.destroy();
        return;
    }
    websocketServer.handleUpgrade(request, socket, head, connection => {
        connection.cmid = payload.cmid;
        websocketServer.emit('connection', connection);
    });
});

websocketServer.on('connection', connection => {
    const room = rooms.get(connection.cmid) || new Set();
    room.add(connection);
    rooms.set(connection.cmid, room);
    connection.send(JSON.stringify({type: 'connected'}));
    connection.on('close', () => {
        room.delete(connection);
        if (room.size === 0) {
            rooms.delete(connection.cmid);
        }
    });
});

const subscriber = createClient({
    url: redisUrl,
    password: redisPassword,
    socket: {reconnectStrategy: retries => Math.min(retries * 250, 5000)}
});

subscriber.on('error', error => console.error('Valkey subscriber error:', error.message));

const start = async () => {
    await subscriber.connect();
    await subscriber.pSubscribe('livecourse:*', (message, channel) => {
        const cmid = Number(channel.slice('livecourse:'.length));
        const room = rooms.get(cmid);
        if (!room) {
            return;
        }
        const payload = JSON.stringify({type: 'refresh'});
        room.forEach(connection => {
            if (connection.readyState === 1) {
                connection.send(payload);
            }
        });
    });
    server.listen(port, '0.0.0.0', () => console.log(`Live course WebSocket gateway listening on ${port}`));
};

start().catch(error => {
    console.error(error);
    process.exit(1);
});
