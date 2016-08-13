"use strict";
var config = require('./config');
var mysql = require('mysql');
var pool  = mysql.createPool(config.db);
var _ = require('lodash');
var User = {};
User.onlineUsers = [];
User.socketUsers = [];

User.updateData = function(u){
    pool.query(`UPDATE user_profiles SET online_time = online_time + ${u.logTime}, messages = messages + ${u.messages} WHERE user_id = ${u.id}` , function(err, result) {
        if (err){
            console.error(err);
            return;
        };
        console.log(`Updating user ${u.user}. Set Logtime ${u.logTime}; Messages ${u.messages}`);
    });
};

User.addPrivateMessage = function(u, to, message){
    let insert = {
        from_id: u.id,
        to_id: to,
        message: message
    }
    if(this.getUserSocket(to) !== null){
        insert.seen = 1;
    }
    pool.query("INSERT INTO `rao_chat`.`private_messages` SET ?", insert, function(err, result){
        if (err){
            console.error(`Error on sending private message from ${u.user}. to_id ${to}; message ${message}; Error`, err);
            return;
        };
    });
};

User.pushData = function(u){
    let index = this.getUserIndexById(u.id);
    if(index === -1){
        this.onlineUsers.push(u);
        return true;
    }
    if(u.private){
        this.onlineUsers[index].private = u.private;
        return true;
    }

    return false;
}

User.pushSocket = function(socket, id, priv){
    this.socketUsers[socket] = {
        id: id,
        private: priv
    };
    return true;
}

User.deleteSocket = function(socket){
    delete this.socketUsers[socket];
}

User.deleteUser = function(id){
    let index = this.getUserIndexById(id);
    if(index === -1){
        return false;
    }
    this.onlineUsers.splice(index, 1);
    return true;
}

User.getUserIndexById = function(id){
    return _.findIndex(this.onlineUsers, (u) => {return u.id == id});
};

User.getUserIndexBySession = function(session){
    return _.findIndex(this.onlineUsers, (u) => {return u.session == session});
};

User.getUserById = function(id){
    let index = _.findIndex(this.onlineUsers, (u) => {return u.id == id});
    return onlineUsers[index];
}

User.generateOnlineUsers = function() {
    var online = [];
    let notPrivates = _.filter(this.onlineUsers, (o) => { return o.private !== true });
    notPrivates.forEach(function(val, index){
        online.push({
            id: val.id,
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
    return online;
}

User.getUserSocket = function(id) {
    if(id === undefined) return null;
    let socketid = null;
    _.forOwn(this.socketUsers, (val, key) => {
        if(id === val.id){
            socketid = {id: key, private: val.private};
            return false;
        }
    });
    return socketid;
}

User.getUserSockets = function(id) {
    if(id === undefined) return [];
    let socketid = [];
    _.forOwn(this.socketUsers, (val, key) => {
        if(id === val.id){
            socketid.push({id: key, private: val.private});
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