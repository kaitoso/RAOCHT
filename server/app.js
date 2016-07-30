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
var subscriber = redis.createClient();
var redisClient = redis.createClient();
var globalUsers = [];
var userMetadata = [];
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
    redisClient.get(sessid, (err, data) => {
        if(err){
            console.log(err);
            return;
        }
        let json = JSON.parse(data);
        json.session = sessid;
        globalUsers[socket.id] = json;
        json.ready = false;
        console.log(globalUsers);
    });
    socket.on('message', (data) => {
        if (typeof(data.message) !== 'string') return;
        if (data.message.length > 255) {
            io.to(socket.id).emit('error', {
                message: "El mensaje es muy grande."
            });
            return;
        }
        let user = globalUsers[socket.id];
        let message = {
            'user': user.user,
            'chatName': user.chatName,
            'chatColor': user.chatColor,
            'chatText': user.chatText,
            'image': user.image,
            'rank': user.rank,
            'message': data.message
        }
        io.emit('message', message);
    });
    socket.on('disconnect', () => {
       console.log('Handle disconect');
        delete globalUsers[socket.id];
        console.log(globalUsers);
    });
});

/* Redis */
subscriber.on('message', (channel, data) => {
   console.log(channel, data);
    let message = JSON.parse(data);

    if(channel === 'update-image'){
        let id = getUserBySession(message.id, globalUsers);
        if(id === null){
            return;
        }
        let user = globalUsers[id];
        user.image = message.image;
        globalUsers[id] = user;
        // Emit user change
    }
    if(channel == 'update-chat'){
        let id = getUserBySession(message.id, globalUsers);
        console.log('id', id);
        if(id === null){
            return;
        }
        let user = globalUsers[id];
        user.chatName = message.chatName;
        user.chatColor = message.chatColor;
        user.chatText = message.chatText;
        globalUsers[id] = user;
    }
});
subscriber.subscribe('update-image', 'update-chat', 'ban-chat');

function getUserById(id, users){
    let globalKey = null;
    _.forOwn(users, (user, key) => {
        if (user.user_id === id) {
            globalKey = key;
            return false;
        }
    });
    return globalKey;
};

function getUserBySession(session, users){
    let globalKey = null;
     _.forOwn(users, (user, key) => {
         if (user.session === session) {
             globalKey = key;
             return false;
         }
     });
    return globalKey;
};