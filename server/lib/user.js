"use strict";
function array_flip(trans) {
    var key, tmp_ar = {};
    for (key in trans) {
        if (trans.hasOwnProperty(key)) {
            tmp_ar[trans[key]] = key;
        }
    }
    return tmp_ar;
}
var config = require('./config');
var mysql = require('mysql');
var pool  = mysql.createPool(config.db);
var _ = require('lodash');
var User = {};
User.onlineUsers = [];
User.currentOnline = [];
User.privateSockets = [];
User.publicSockets = [];
User.rankPermissions = {};

User.updateData = function(u){
    let currentTime = _.now() - u.logTime;
    pool.query(`UPDATE user_profiles SET online_time = online_time + ${currentTime}, messages = messages + ${u.messages} WHERE user_id = ${u.id}`, (err, result) => {
        if (err){
            console.error('updateDate ',err);
            return;
        };
        console.log(`Updating user ${u.user}. Set Logtime ${currentTime}; Messages ${u.messages}`);
    });
};

User.getRankData = function () {
    pool.query('SELECT id, immunity, permissions, chatPermissions FROM ranks;', (err, rows, fields) => {
       if(err) {
           console.error('getRankPermissions ', err);
           return;
       }
       for(let i in rows){
           this.rankPermissions[rows[i].id] = {
               immunity: rows[i].immunity,
               permission: array_flip(JSON.parse(rows[i].permissions)),
               chatPermission: array_flip(JSON.parse(rows[i].chatPermissions))
           }
       }
    });
}

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

User.getUserIndexByName = function(name){
    return _.findIndex(this.onlineUsers, (u) => {return u.user.toLowerCase() === name.toLowerCase()});
}

User.getUserByName = function(name){
    let index = this.getUserIndexByName(name);
    if(index === -1){
        return null;
    }
    return this.onlineUsers[index];
}

User.getRanksWithImmunity = function(){
    let ranks = [];
    _.forOwn(this.rankPermissions, (r, key) => {
        if(r.immunity){
            ranks.push(parseInt(key));
        }
    });
    return ranks;
}

/**
 * Genera los usuarios en línea basado en los sockets públicos.
 * @returns {Array}
 */
User.generateOnlineUsers = function() {
    var online = [];
    let usedIds = [];
    _.forOwn(this.publicSockets, (val, key) => {
        if(usedIds[val] === undefined){
            let user = this.getUserById(val);
            online.push({
                id: user.id,
                user: user.user,
                image: user.image
            });
            usedIds[val] = true;
        }
    })
    online.sort((a, b) => {
        if (a.user > b.user)
            return 1;
        if (a.user < b.user)
            return -1;
        return 0;
    });
    this.currentOnline = online;
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
        if(id === val){
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
        if(id === val){
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
    _.forOwn(this.privateSockets, (val, key) => {
        if(id === val){
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
    _.forOwn(this.privateSockets, (val, key) => {
        if(id === val){
            socketid.push(key);
        }
    });
    return socketid;
}


module.exports = User;