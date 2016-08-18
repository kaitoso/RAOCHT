"use strict";
var config = require('./config');
var User = require('./user');
var escape = require('escape-html');
var _ = require('lodash');
var cookie = require('cookie');
var redis = require('redis');
var redisClient = redis.createClient(config.redis);
var PrivIO;
function Privado(io, ChatIO) {
    PrivIO = io.of('/privado');
    PrivIO.on('connection', (socket) => {
        let cookies = cookie.parse(socket.handshake.headers.cookie);
        if(cookies.rao_session === undefined){
            console.error('Undefined session', socket.handshake.headers.cookie);
            PrivIO.to(socket).emit('restart');
            socket.disconnect();
            return;
        }
        let sessid = cookies.rao_session;
        let currentUser = null;
        redisClient.get(sessid, (err, data) => {
            if(err){
                console.log(err);
                socket.disconnect();
                return;
            }
            if(data == null){
                console.error('Null value. Sessid: ', sessid, cookies);
                PrivIO.to(socket).emit('restart');
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
            if(User.pushData(json)){
                console.log(`Added user private ${json.user}`);
            }
            User.pushSocket(socket.id, json.id, true);
        });
        socket.on('message', (data) => {
            if (typeof(data.message) !== 'string') return;
            if(typeof(data.to) !== 'number') return;
            if (data.message.length > 255) {
                PrivIO.to(socket.id).emit('error', {
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
            if(data.to == currentUser.id){
                PrivIO.to(socket.id).emit('error', {
                    message: "¿Enviandote un mensaje a ti mismo?"
                });
                return;
            }
            let remoteSockets = User.getPubSocketsById(data.to);
            let mySockets = User.getPubSocketsById(currentUser.id);
            let message = {
                'id': currentUser.id,
                'user': currentUser.user,
                'chatName': currentUser.chatName,
                'chatColor': currentUser.chatColor,
                'chatText': currentUser.chatText,
                'image': currentUser.image,
                'rank': currentUser.rank,
                'message': escape(data.message)
            }
            User.addPrivateMessage(currentUser, data.to, data.message);
            currentUser.last = _.now();
            PrivIO.to(socket.id).emit('message', message);
            if(!remoteSockets.length){
                return;
            }
            let sendToMain = true;
            let privates = User.getPrivSocketsById(data.to);
            if(privates.length > 0){
                _.each(privates, function(val, index){
                    PrivIO.to(val).emit('message', message);
                    sendToMain = false;
                });
            }else{
                _.each(remoteSockets, function(val, index){
                    ChatIO.to(val).emit('privado', message);
                });
            }
            if(mySockets.length > 0){
                _.each(mySockets, function (val, index) {
                    ChatIO.to(val).emit('activity');
                })
            }
        });

        socket.on('ready', () => {
            if(currentUser === undefined){
                console.error('Undefined user on Ready');
                socket.disconnect();
            }
            if(currentUser.ready){
                PrivIO.to(socket.id).emit('system', {
                    message: '¿Para que intentas enviar de nuevo esta petición?'
                });
                return;
            }
            currentUser.ready = true;
        });

        socket.on('disconnect', () => {
            let socketUser = User.privateSockets[socket.id];
            if(socketUser === undefined) return;
            let sockets = User.getPrivSocketsById(socketUser);
            let userIndex = User.getUserIndexById(socketUser);
            let user = User.onlineUsers[userIndex];
            if(sockets.length > 1){
                User.deletePrivateSocket(socket.id);
            }else{
                let pubSocket = User.getPubSocketsById(user.id);
                if(pubSocket !== undefined && pubSocket.length > 0){
                    User.deletePrivateSocket(socket.id);
                }else{
                    User.deleteUser(user.id)
                    User.deletePrivateSocket(socket.id);
                    ChatIO.emit('online', User.generateOnlineUsers()); // Volvemos a generar los usuarios conectados.
                }
            }
        });
    });
}

Privado.prototype.getIO = function(){
    return PrivIO;
}

Privado.prototype.sendMessage = function(socket, event, message){
    PrivIO.to(socket).emit(event, message);
}

Privado.prototype.disconnect = function(socket){
    PrivIO.to(socket).emit('offline');
    PrivIO.sockets.connected[socket].disconnect();
}


module.exports = Privado;