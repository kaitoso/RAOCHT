"use strict";
var _ = require('lodash');
var User = require('./user');
var escape = require('escape-html');
var parse = function(str, lookForQuotes) {
    var args = [];
    var readingPart = false;
    var part = '';
    for (var i = 0; i < str.length; i++) {
        if (str.charAt(i) === ' ' && !readingPart) {
            args.push(part);
            part = '';
        } else {
            if (str.charAt(i) === '\"' && lookForQuotes) {
                readingPart = !readingPart;
            } else {
                part += str.charAt(i);
            }
        }
    }
    args.push(part);
    return args;
};
var parseTime = function(time) {
    var msec = time;
    var time = '';
    var day = Math.floor(msec / 1000 / 60 / 60 / 24);
    msec -= day * 1000 * 60 * 60 * 24;
    var hh = Math.floor(msec / 1000 / 60 / 60);
    msec -= hh * 1000 * 60 * 60;
    var mm = Math.floor(msec / 1000 / 60);
    msec -= mm * 1000 * 60;
    var ss = Math.floor(msec / 1000);
    if(day > 0){
        time += day + ((day === 1) ? ' día ' : ' días ');
    }
    if(hh > 0){
        time += hh + ' horas ';
    }
    if(mm > 0){
        time += mm + ' minutos ';
    }
    time += ss + ' segundos.';
    return time;
};
module.exports = function (ChatIO) {
    var comandos = {};

    comandos.parse = function(socket, user, message){
        let comando = parse(message);
        switch (comando.shift()){
            case "/kick":
                this.kick(socket, user, comando);
                break;
            case "/global":
                this.global(socket, user, comando);
                break;
            case "/reiniciar":
                this.restart(socket, user, comando);
                break;
            case "/stat":
                this.stat(socket, user, comando);
                break;
            case "/clean":
                this.clean(socket, user, comando);
                break;
            default:
                let sendMessage = {
                    'user': user.user,
                    'chatName': user.chatName,
                    'chatColor': user.chatColor,
                    'chatText': user.chatText,
                    'image': user.image,
                    'rank': user.rank,
                    'message': escape(message)
                }
                ChatIO.emit('message', sendMessage);
        }
    };

    comandos.kick = function(socket, user, comando){
        let userRank = User.rankPermissions[user.rank];
        if(userRank.permission.ban === undefined){
            ChatIO.to(socket.id).emit('system', {
                message: "No tienes los suficientes permisos para ejecutar este comando."
            });
            return;
        }
        if (comando.length === 0){
            ChatIO.to(socket.id).emit('system', {
                message: "Por favor escribe un usuario y una razón. Ejemplo: /kick usuario razon"
            });
            return;
        }
        var dest = comando.shift();
        if(dest.toUpperCase() === user.user.toUpperCase()){
            ChatIO.emit('system', {
                message: `El usuario ${user.user} se intentó patear a sí mismo. ¡Que tristeza!`
            });
            return;
        }
        var razon = comando.join(" ");
        if (dest === "all" && user.rank === 1) {
            _.forEach(User.onlineUsers, (u, key) => {
                let currentRank = User.rankPermissions[u.rank];
                if(currentRank.immunity){
                    return;
                }
                let pubSockets = User.getPubSocketsById(u.id);
                _.forEach(pubSockets, (pubSocket) => {
                    ChatIO.to(pubSocket).emit('kick', {
                        reason: 'El administrador sacó a todos los usuarios.'
                    });
                });
            });
            return;
        }
        let userKickData = User.getUserByName(dest);
        if(userKickData === null){
            ChatIO.to(socket.id).emit('system', {
                message: "Este usuario no se encuentra conectado."
            });
            return;
        }
        if(_.isUndefined(razon) || _.isNull(razon) || razon === ""){
            ChatIO.to(socket.id).emit('system', {
                message: "Escribe una razón después del nombre del usuario."
            });
            return;
        }
        let immunityRank = User.getRanksWithImmunity();
        if(_.includes(immunityRank, userKickData.rank)){
            ChatIO.to(socket.id).emit('system', {
                message: "Este usuario tiene inmunidad para ser pateado."
            });
            return;
        }
        let pubSockets = User.getPubSocketsById(userKickData.id);
        _.forEach(pubSockets, (pubSocket) => {
            ChatIO.to(pubSocket).emit('kick', {
                reason: razon
            });
        });
        ChatIO.emit('system', {
            message: `El usuario ${userKickData.user} ha sido pateado por el usuario ${user.user} por la razón de: ${razon}`
        });
    };

    comandos.stat = function(socket, user, comando) {
        var currentTime = user.profile.time + (_.now() - user.logTime);
        ChatIO.to(socket.id).emit('system', {
            message: `Tu tiempo conectado es de: ${parseTime(currentTime)}`
        });
    };

    comandos.global = function(socket, user, comando){
        let userRank = User.rankPermissions[user.rank];
        if(userRank.permission.global === undefined){
            ChatIO.to(socket.id).emit('system', {
                message: "No tienes los suficientes permisos para ejecutar este comando."
            });
            return;
        }
        if (comando.length === 0){
            ChatIO.to(socket.id).emit('system', {
                message: "Escribe al menos el mensaje para el global"
            });
            return;
        }
        let mensaje = comando.join(' ');
        ChatIO.emit('global', {
            user: user.user,
            message: escape(mensaje)
        });
    }

    comandos.restart = function (socket, user, comando) {
        let userRank = User.rankPermissions[user.rank];
        if(userRank.permission.ban === undefined){
            ChatIO.to(socket.id).emit('system', {
                message: "No tienes los suficientes permisos para ejecutar este comando."
            });
            return;
        }
        if (comando.length === 0){
            ChatIO.to(socket.id).emit('system', {
                message: "Por favor escribe un usuario y una razón. Ejemplo: /kick usuario razon"
            });
            return;
        }
        var dest = comando.shift();
        if (dest === "all" && user.rank === 1) {
            _.forEach(User.onlineUsers, (u, key) => {
                let currentRank = User.rankPermissions[u.rank];
                if(currentRank.immunity){
                    return;
                }
                let pubSockets = User.getPubSocketsById(u.id);
                _.forEach(pubSockets, (pubSocket) => {
                    ChatIO.to(pubSocket).emit('restart');
                });
            });
            return;
        }
        let userKickData = User.getUserByName(dest);
        if(userKickData === null){
            ChatIO.to(socket.id).emit('system', {
                message: "Este usuario no se encuentra conectado."
            });
            return;
        }
        let immunityRank = User.getRanksWithImmunity();
        if(_.includes(immunityRank, userKickData.rank)){
            ChatIO.to(socket.id).emit('system', {
                message: "Este usuario tiene inmunidad para ser reiniciado."
            });
            return;
        }
        let pubSockets = User.getPubSocketsById(userKickData.id);
        _.forEach(pubSockets, (pubSocket) => {
            ChatIO.to(pubSocket).emit('restart');
        });
    }

    comandos.clean = function(socket, user, comando){

    }

    return comandos;
};
