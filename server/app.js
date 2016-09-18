"use strict";
process.title = "RAO-CHAT";
process.on('exit', (code) => {
    console.log('Saliendo...', code);
});
var config = require('./lib/config');
var stream = require('./lib/stream');
var server = require('http').createServer(httpHandler);
var redis = require('redis');
var io = require('socket.io')(server);
var ChatIO = io.of('/chat');
var Privado = require('./lib/privado')(io, ChatIO);
var fs = require('fs');
var _ = require('lodash');
var cookie = require('cookie');
var escape = require('escape-html');
var User = require('./lib/user');
var Commands = require('./lib/comandos')(ChatIO);
var subscriber = redis.createClient(config.redis);
var redisClient = redis.createClient(config.redis);
var chatConfig = {
    start: _.now(),
    message: '¡Bienvenido al chat de Radio Anime Obsesión!',
    sessionMessages: 0
};
server.listen(config.app.port);
fs.readFile(__dirname + '/../src/rao/Config/Chat.json', (err, data) => {
        if (err) {
            console.error('Error reading chat config file.');
            return;
        }
        let json = JSON.parse(data);
        if(json.message !== undefined){
            chatConfig.message = json.message;
        }
    }
);
stream.getStreamData(ChatIO);

setInterval(function() {
    stream.getStreamData(ChatIO);
}, 30000);

function httpHandler (req, res) {
    res.setHeader('content-type', 'application/json; charset=utf-8');
    res.writeHead(200);
    res.end(JSON.stringify({
        users: User.onlineUsers.length,
        messages: {
            count: chatConfig.sessionMessages,
            start: chatConfig.start
        },
        stream: stream.getData()
    }));
}

User.getRankData();
ChatIO.on('connection', (socket) => {
    let cookies = cookie.parse(socket.handshake.headers.cookie);
    if(cookies.rao_session === undefined){
        console.error('Undefined session', socket.handshake.headers.cookie);
        ChatIO.to(socket.id).emit('restart');
        socket.disconnect();
        return;
    }
    let sessid = cookies.rao_session;
    let currentUser = null;
    redisClient.get(sessid, (err, data) => {
        if(err){
            console.log(err);
            ChatIO.to(socket.id).emit('restart');
            socket.disconnect();
            return;
        }
        if(data == null){
            console.error('Null value. Sessid: ', sessid, cookies);
            ChatIO.to(socket.id).emit('restart');
            socket.disconnect();
            return;
        }
        let json = JSON.parse(data);
        json.session = sessid;
        json.last = _.now();
        json.messages = 0;
        json.logTime = _.now();
        json.ready = false;
        currentUser = json;
        User.pushSocket(socket.id, json.id, false);
        if(User.pushData(json)){
            console.log(`Added user public ${json.user}`);
            User.generateOnlineUsers();
            ChatIO.emit('online', User.currentOnline); // Volvemos a generar los usuarios conectados.
        }else{
            ChatIO.to(socket.id).emit('online', User.currentOnline);
        }
    });
    socket.on('message', (data) => {
        if (typeof(data.message) !== 'string') return;
        if (data.message.length > 255) {
            ChatIO.to(socket.id).emit('error', {
                message: "El mensaje es muy grande."
            });
            return;
        }
        if(currentUser === undefined){
            console.error('Undefined user on Message');
            socket.disconnect();
            return;
        }
        if(_.now() - currentUser.last < 250){
            return;
        }
        console.log('[Message]', currentUser.user, ':', data.message);
        Commands.parse(socket, currentUser, data.message);
        currentUser.last = _.now();
        currentUser.messages++;
        chatConfig.sessionMessages++;
    });

    socket.on('ready', () => {
        if(currentUser === undefined){
            console.error('Undefined user on Ready');
            socket.disconnect();
        }
        if(currentUser.ready){
            ChatIO.to(socket.id).emit('system', {
                message: '¿Para que intentas enviar de nuevo esta petición?'
            });
            return;
        }
        ChatIO.to(socket.id).emit('system', {
            message: chatConfig.message
        });
        ChatIO.to(socket.id).emit('system', {
            message: '¡Ahora locuta ' + stream.announcer + '!'
        });
        currentUser.ready = true;
    });

    socket.on('disconnect', () => {
        let socketUserId = User.publicSockets[socket.id];
        if(socketUserId === undefined) return;
        let sockets = User.getPubSocketsById(socketUserId);
        let userIndex = User.getUserIndexById(socketUserId);
        let user = User.onlineUsers[userIndex];
        if(sockets.length > 1){
            User.deletePublicSocket(socket.id);
        }else{
            let privSocket = User.getPrivSocketsById(user.id);
            if(privSocket !== undefined && privSocket.length > 0){
                User.deletePublicSocket(socket.id);
            }else{
                User.updateData(user);
                User.deleteUser(user.id)
                User.deletePublicSocket(socket.id);
            }
            ChatIO.emit('online', User.generateOnlineUsers()); // Volvemos a generar los usuarios conectados.
        }
        console.log(`Disconnection user ${currentUser.user} [Public]`);
    });
});

/* Redis */
subscriber.on('message', (channel, data) => {
   console.log(channel, data);
    let message = JSON.parse(data);
    if(channel === 'admin-update-background'){
        ChatIO.emit('background', message);
    }
    if(channel === 'update-image'){
        let index = User.getUserIndexBySession(message.id);
        if(index === -1) return;
        let user = User.onlineUsers[index];
        if(user === undefined) return;
        let socket = User.getPubSocketById(user.id);
        if(socket === null) return;
        user.image = message.image;
        User.onlineUsers[index] = user;
        // Emit user change
        let newUser = {
            'user': user.user,
            'chatName': user.chatName,
            'chatColor': user.chatColor,
            'chatText': user.chatText,
            'image': user.image,
            'rank': user.rank,
        }
        ChatIO.to(socket).emit('update', newUser);
        ChatIO.emit('online', User.generateOnlineUsers());
    }
    if(channel == 'update-client'){
        ChatIO.emit('client-update', message);
        User.getRankData();
    }
    if(channel === 'update-chat'){
        let index = User.getUserIndexBySession(message.id);
        if(index === -1) return;
        let user = User.onlineUsers[index];
        if(user === undefined) return;
        let socket = User.getPubSocketById(user.id);
        if(socket === null) return;
        user.chatName = message.chatName;
        user.chatColor = message.chatColor;
        user.chatText = message.chatText;
        User.onlineUsers[index] = user;
        // Emit user change
        let newUser = {
            'user': user.user,
            'chatName': user.chatName,
            'chatColor': user.chatColor,
            'chatText': user.chatText,
            'image': user.image,
            'rank': user.rank,
        }
        ChatIO.to(socket).emit('update', newUser);
    }

    if(channel === 'user-achievement'){
        let index = User.getUserIndexById(message.user_id);
        if(index === null) return;
        let user = User.onlineUsers[index];
        if(user === undefined) return;
        let socket = User.getPubSocketById(user.id);
        if(socket === null) return;
        ChatIO.to(socket).emit('achievement', {
            achievement: true
        });
    }

    if(channel === 'global-achievement'){
        ChatIO.emit('achievement', {
            achievement: true,
            id: message.logro_id
        });
    }

    if(channel === 'admin-update-image'){
        let index = User.getUserIndexById(message.id);
        if(index === null) return;
        let user = User.onlineUsers[index];
        if(user === undefined) return;
        let socket = User.getPubSocketById(user.id);
        if(socket === null) return;
        user.image = message.image;
        User.onlineUsers[index] = user;
        // Emit user change
        let newUser = {
            'user': user.user,
            'chatName': user.chatName,
            'chatColor': user.chatColor,
            'chatText': user.chatText,
            'image': user.image,
            'rank': user.rank,
        }
        ChatIO.to(socket).emit('update', newUser);
        ChatIO.emit('online', User.generateOnlineUsers());
    }

    if(channel === 'admin-update-user'){
        let index = User.getUserIndexById(message.id);
        if(index === null) return;
        let user = User.onlineUsers[index];
        if(user === undefined) return;
        let socket = User.getPubSocketById(user.id);
        if(socket === null) return;
        user.user = message.user;
        user.rank = message.rank;
        User.onlineUsers[index] = user;
        // Emit user change
        let newUser = {
            'user': user.user,
            'chatName': user.chatName,
            'chatColor': user.chatColor,
            'chatText': user.chatText,
            'image': user.image,
            'rank': user.rank,
        }
        ChatIO.to(socket).emit('update', newUser);
    }

    if(channel === 'admin-update-chat'){
        let index = User.getUserIndexById(message.id);
        if(index === null) return;
        let user = User.onlineUsers[index];
        if(user === undefined) return;
        let socket = User.getPubSocketById(user.id);
        if(socket === null) return;
        user.chatName = message.chatName;
        user.chatColor = message.chatColor;
        user.chatText = message.chatText;
        User.onlineUsers[index] = user;
        // Emit user change
        let newUser = {
            'user': user.user,
            'chatName': user.chatName,
            'chatColor': user.chatColor,
            'chatText': user.chatText,
            'image': user.image,
            'rank': user.rank,
        }
        ChatIO.to(socket).emit('update', newUser);
    }

    if(channel === 'admin-update-welcome'){
        chatConfig.message = message.message;
    }

    if(channel === 'ban-chat'){
        let socket = User.getPubSocketsById(message.id);
        if(socket.length === 0) return;
        socket.forEach(function(val, index){
            try{
                ChatIO.to(val).emit('offline');
                ChatIO.sockets.connected[val].disconnect();
            }catch(e){
                console.error('Exception Ban-Chat', e);
            }
        });
        // Add private ban
    }

    if(channel === 'admin-global'){
        ChatIO.emit('global', {
            user: message.user,
            message: escape(message.message)
        });
    }

});
subscriber.subscribe(
    'admin-update-background',
    'update-image',
    'update-client',
    'update-chat',
    'user-achievement',
    'global-achievement',
    'admin-update-image',
    'admin-update-user',
    'admin-update-chat',
    'admin-update-welcome',
    'ban-chat',
    'admin-global'
);