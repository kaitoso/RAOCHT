"use strict";
process.title = "RAO-CHAT";
process.on('exit', (code) => {
    console.log('Saliendo...');
});
var server = require('http').createServer(httpHandler);
var redis = require('redis');
var io = require('socket.io')(server);
var fs = require('fs');
var _ = require('lodash');
var cookie = require('cookie');
var escape = require('escape-html');
var subscriber = redis.createClient();
var redisClient = redis.createClient();
var globalUsers = [];
var userData = [];
server.listen(8080);


function httpHandler (req, res) {
    fs.readFile(__dirname + '/index.html', (err, data) => {
            if (err) {
                res.writeHead(500);
                return res.end('Error loading index.html');
            }
            res.writeHead(200);
            res.end(data);
        }
    );
}

io.on('connection', (socket) => {
    let cookies = cookie.parse(socket.handshake.headers.cookie);
    let sessid = cookies.rao_session;
    let currentUser = null;
    if(sessid == null){
        socket.disconnect();
        return;
    }
    redisClient.get(sessid, (err, data) => {
        if(err){
            console.log(err);
            return;
        }
        let json = JSON.parse(data);
        json.session = sessid;
        json.last = _.now();
        currentUser = json;
        userData.push(json);
        globalUsers[socket.id] = json.id;
        generateOnlineUsers(userData);
    });
    socket.on('message', (data) => {
        if (typeof(data.message) !== 'string') return;
        if (data.message.length > 255) {
            io.to(socket.id).emit('error', {
                message: "El mensaje es muy grande."
            });
            return;
        }
        if(_.now() - currentUser.last < 250){
            return;
        }
        let message = {
            'user': currentUser.user,
            'chatName': currentUser.chatName,
            'chatColor': currentUser.chatColor,
            'chatText': currentUser.chatText,
            'image': currentUser.image,
            'rank': currentUser.rank,
            'message': escape(data.message)
        }
        io.emit('message', message);
        currentUser.last = _.now();
    });
    socket.on('disconnect', () => {
       console.log('Handle disconect');
        let userId = globalUsers[socket.id];
        if(userId != null){
            let index = getUserIndexById(userId, userData);
            userData.splice(index, 1);
            delete globalUsers[socket.id];
        }
        generateOnlineUsers(userData);
    });
});

/* Redis */
subscriber.on('message', (channel, data) => {
   console.log(channel, data);
    let message = JSON.parse(data);
    if(channel === 'admin-update-background'){
        io.emit('background', message);
    }
    if(channel === 'update-image'){
        let index = getUserIndexBySession(message.id, userData);
        if(index === -1) return;
        let user = userData[index];
        let socket = getUserSocket(user.id, globalUsers);
        if(socket === null) return;
        user.image = message.image;
        userData[index] = user;
        // Emit user change
        let newUser = {
            'user': user.user,
            'chatName': user.chatName,
            'chatColor': user.chatColor,
            'chatText': user.chatText,
            'image': user.image,
            'rank': user.rank,
        }
        io.to(socket).emit('update', newUser);
    }
    if(channel == 'update-client'){
        io.emit('client-update', message);
    }
    if(channel === 'update-chat'){
        let index = getUserIndexBySession(message.id, userData);
        if(index === -1) return;
        let user = userData[index];
        let socket = getUserSocket(user.id, globalUsers);
        if(socket === null) return;
        user.chatName = message.chatName;
        user.chatColor = message.chatColor;
        user.chatText = message.chatText;
        userData[index] = user;
        // Emit user change
        let newUser = {
            'user': user.user,
            'chatName': user.chatName,
            'chatColor': user.chatColor,
            'chatText': user.chatText,
            'image': user.image,
            'rank': user.rank,
        }
        io.to(socket).emit('update', newUser);
    }

    if(channel === 'user-achievement'){
        let index = getUserIndexById(message.user_id, userData);
        if(index === null) return;
        let user = userData[index];
        let socket = getUserSocket(user.id, globalUsers);
        if(socket.length === 0) return;
        io.to(socket).emit('achievement', {
            achievement: true
        });
    }

    if(channel === 'global-achievement'){
        io.emit('achievement', {
            achievement: true,
            id: message.logro_id
        });
    }

    if(channel === 'admin-update-image'){
        let index = getUserIndexById(message.id, userData);
        if(index === null) return;
        let user = userData[index];
        let socket = getUserSocket(user.id, globalUsers);
        if(socket.length === 0) return;
        user.image = message.image;
        userData[index] = user;
        // Emit user change
        let newUser = {
            'user': user.user,
            'chatName': user.chatName,
            'chatColor': user.chatColor,
            'chatText': user.chatText,
            'image': user.image,
            'rank': user.rank,
        }
        io.to(socket).emit('update', newUser);
    }

    if(channel === 'admin-update-user'){
        let index = getUserIndexById(message.id, userData);
        if(index === null) return;
        let user = userData[index];
        let socket = getUserSocket(user.id, globalUsers);
        if(socket.length === 0) return;
        user.user = message.user;
        user.rank = message.rank;
        userData[index] = user;
        // Emit user change
        let newUser = {
            'user': user.user,
            'chatName': user.chatName,
            'chatColor': user.chatColor,
            'chatText': user.chatText,
            'image': user.image,
            'rank': user.rank,
        }
        io.to(socket).emit('update', newUser);
    }

    if(channel === 'admin-update-chat'){
        let index = getUserIndexById(message.id, userData);
        if(index === null) return;
        let user = userData[index];
        let socket = getUserSocket(user.id, globalUsers);
        if(socket.length === 0) return;
        user.chatName = message.chatName;
        user.chatColor = message.chatColor;
        user.chatText = message.chatText;
        userData[index] = user;
        // Emit user change
        let newUser = {
            'user': user.user,
            'chatName': user.chatName,
            'chatColor': user.chatColor,
            'chatText': user.chatText,
            'image': user.image,
            'rank': user.rank,
        }
        io.to(socket).emit('update', newUser);
    }

    if(channel === 'ban-chat'){
        let index = getUserIndexById(message.id, userData);
        if(index === null) return;
        let user = userData[index];
        let socket = getUserSockets(user.id, globalUsers);
        if(socket.length === 0) return;
        socket.forEach(function(val, index){
            io.to(val).emit('offline');
            io.sockets.connected[val].disconnect();
        });
    }

    if(channel == 'admin-global'){
        io.emit('global', {
            user: message.user,
            message: message.message
        });
    }

});
subscriber.subscribe(
    'admin-update-background', // Actualización del fondo
    'update-image',
    'update-client',
    'update-chat',
    'user-achievement',
    'global-achievement',
    'admin-update-image',
    'admin-update-user',
    'admin-update-chat',
    'ban-chat',
    'admin-global'
);

function getUserIndexById(id, users){
    return _.findIndex(users, (u) => {return u.id == id});
};

function getUserIndexBySession(session, users){
    return _.findIndex(users, (u) => {return u.session == session});
};

function getUserById(id, users){
    let index = _.findIndex(users, (u) => {return u.id == id});
    return users[index];
}

function generateOnlineUsers(users) {
    var online = [];
    users.forEach(function(val, index){
        online.push({
            user: val.user,
            image: val.image
        });
    });
    online.sort((a, b) => {
        if (a.user > b.user)
            return 1;
        if (a.user < b.user)
            return -1;
        return 0;
    })
    io.sockets.emit('online', online);
}

function getUserSocket(id, users) {
    let socketid = null;
    _.forOwn(users, (val, key) => {
        console.log(id, val, key);
        if(id === val){
            socketid = key;
            return false;
        }
    });
    return socketid;
}

function getUserSockets(id, users) {
    let socketid = [];
    _.forOwn(users, (val, key) => {
        console.log(id, val, key);
        if(id === val){
            socketid.push(key);
        }
    });
    return socketid;
}

function getIndexSocket(id, users) {
    let index = -1;
    var found = false;
    _.forOwn(users, (userid, key) => {
        console.log(id, userid, key);
        index++;
        if(id === userid){
            found = true;
            return false;
        }
    });
    if(found)
        return index;
    else
        return -1;
}