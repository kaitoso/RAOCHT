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
            json.private = true;
            json.ready = false;
            currentUser = json;
            if(User.pushData(json)){
                console.log(`Added user ${json.user}`, json);
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
            let remoteSockets = User.getUserSockets(data.to);
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
            _.each(remoteSockets, function(val, index){
                if(val.private){
                    PrivIO.to(val.id).emit('message', message);
                    sendToMain = false;
                }
                if(sendToMain){
                    ChatIO.to(val.id).emit('privado', message);
                }
            });
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
            let userId = User.socketUsers[socket.id];
            let sockets = User.getUserSockets(userId);
            if(sockets.length > 1){
                User.deleteSocket(socket.id);
            }else if(userId != null){
                User.deleteUser(userId)
                User.deleteSocket(socket.id);
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