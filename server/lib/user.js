"use strict";
var config = require('./config');
var mysql = require('mysql');
var pool  = mysql.createPool(config.db);
var _ = require('lodash');
var User = {};
User.onlineUsers = [];
User.socketUsers = [];
User.privateSockets = [];
User.publicSockets = [];

User.updateData = function(u){
    pool.query(`UPDATE user_profiles SET online_time = online_time + ${u.logTime}, messages = messages + ${u.messages} WHERE user_id = ${u.id}` , function(err, result) {
        if (err){
            console.error(err);
            return;
        };
        console.log(`Updating user ${u.user}. Set Logtime ${u.logTime}; Messages ${u.messages}`);
    });
};

/**
 * Agrega el mensaje privado a la base de datos.
 * @param u Objeto del usuario
 * @param to ID del usuario aquien va el mensaje.
 * @param message Mensaje
 */
User.addPrivateMessage = function(u, to, message){
    let insert = {
        from_id: u.id,
        to_id: to,
        message: message
    }
    if(this.getPrivSocketById(to) !== null){
        insert.seen = 1;
    }
    pool.query("INSERT INTO `rao_chat`.`private_messages` SET ?", insert, function(err, result){
        if (err){
            console.error(`Error on sending private message from ${u.user}. to_id ${to}; message ${message}; Error`, err);
            return;
        };
    });
};

/**
 * Agrega la información del usuario al vector
 * @param u
 * @returns {boolean}
 */
User.pushData = function(u){
    let index = this.getUserIndexById(u.id);
    if(index === -1){
        this.onlineUsers.push(u);
        return true;
    }
    return false;
}

/**
 * Agrega el socket al vector de sockets (privado o publico)
 * @param socket ID del socket
 * @param id ID del usuario
 * @param priv Valor para verificar si el socket es publico o privado
 * @returns {boolean}
 */
User.pushSocket = function(socket, id, priv){
    if(priv){
        this.privateSockets[socket] = id;
    }else{
        this.publicSockets[socket] = id
    }
    return true;
}

/**
 * Borra el socket publico
 * @param socket
 */
User.deletePublicSocket = function(socket){
    delete this.publicSockets[socket];
}

/**
 * Borra el socket privado
 * @param socket
 */
User.deletePrivateSocket = function(socket){
    delete this.privateSockets[socket];
}

/**
 * Elimina el usuario del vector
 * @param id ID del usuario
 * @returns {boolean} Verdadero si el usuario se encuentra conectado, falso si no.
 */
User.deleteUser = function(id){
    let index = this.getUserIndexById(id);
    if(index === -1){
        return false;
    }
    this.onlineUsers.splice(index, 1);
    return true;
}

/**
 * Obtiene el índice del usuario dentro del vector.
 * @param id ID del usuario
 * @returns {number} Índice
 */
User.getUserIndexById = function(id){
    return _.findIndex(this.onlineUsers, (u) => {return u.id == id});
};

/**
 * Obtiene el índice del usuario dentro del vector.
 * @param session sessiond_id del usuario
 * @returns {number} Índice
 */
User.getUserIndexBySession = function(session){
    return _.findIndex(this.onlineUsers, (u) => {return u.session == session});
};

/**
 * Obtiene el usuario por id
 * @param id ID del usuario.
 * @returns {number}, {undefined}
 */
User.getUserById = function(id){
    let index = _.findIndex(this.onlineUsers, (u) => {return u.id == id});
    return this.onlineUsers[index];
}

/**
 * Genera los usuarios en línea basado en los sockets públicos.
 * @returns {Array}
 */
User.generateOnlineUsers = function() {
    var online = [];
    let usedIds = [];
    this.publicSockets.forEach(function(val, index){
        if(usedIds[val] !== undefined){
            let user = this.getUserById(val);
            online.push({
                id: user.id,
                user: user.user,
                image: user.image
            });
            usedIds[val] = true;
        }
    });
    online.sort((a, b) => {
        if (a.user > b.user)
            return 1;
        if (a.user < b.user)
            return -1;
        return 0;
    })
    return online;
}
/**
 * Obtiene el primer socket del usuario.
 * @param id usuario
 * @returns {number} ID del socket, si no, un valor {null}
 */
User.getPubSocketById = function(id) {
    if(id === undefined) return null;
    let socketid = null;
    _.forOwn(this.publicSockets, (val, key) => {
        if(id === val.id){
            socketid = key;
            return false;
        }
    });
    return socketid;
}

/**
 * Obtiene los sockets publicos referente al id del usuario
 * @param id ID del usuario
 * @returns {Array}
 */
User.getPubSocketsById = function(id) {
    if(id === undefined) return [];
    let socketid = [];
    _.forOwn(this.publicSockets, (val, key) => {
        if(id === val.id){
            socketid.push(key);
        }
    });
    return socketid;
}

/**
 * Obtiene los sockets privados referente al id del usuario
 * @param id ID del usuario
 * @returns {*} ID del socket, si no, un valor nulo
 */
User.getPrivSocketById = function(id) {
    if(id === undefined) return null;
    let socketid = null;
    _.forOwn(this.publicSockets, (val, key) => {
        if(id === val.id){
            socketid = key;
            return false;
        }
    });
    return socketid;
}

/**
 * Obtiene los sockets privados referente al id del usuario
 * @param id ID del usuario
 * @returns {Array}
 */
User.getPrivSocketsById = function(id) {
    if(id === undefined) return [];
    let socketid = [];
    _.forOwn(this.publicSockets, (val, key) => {
        if(id === val.id){
            socketid.push(key);
        }
    });
    return socketid;
}

User.getIndexSocket = function(id) {
    if(id === undefined) return -1;
    let index = -1;
    var found = false;
    _.forOwn(this.socketUsers, (val, key) => {
        index++;
        if(id === val.id){
            found = true;
            return false;
        }
    });
    if(found)
        return index;
    else
        return -1;
}


module.exports = User;