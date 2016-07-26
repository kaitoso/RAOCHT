"use strict";
process.title = "RAO-CHAT";
process.on('exit', (code) => {
    console.log('Saliendo...');
});
var server = require('http').createServer(httpHandler);
var redis = require('redis');
var io = require('socket.io')(server);
var fs = require('fs');
var cookie = require('cookie');
var subscriber = redis.createClient();
var redisClient = redis.createClient();
var globalUsers = [];
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
        console.log(globalUsers);
    });
    socket.on('message', (data) => {
        console.log(data);
        if (typeof(data.message) !== 'string') return;
        if (data.message.length > 255) {
            io.to(socket.id).emit('error', {
                message: "El mensaje es muy grande."
            });
            return;
        }
        let user = globalUsers[socket.id];
        user.message = data.message;
        delete user['session'];
        delete user['user_id'];
        io.emit('message', user);
    });
    socket.on('disconnect', () => {
       console.log('Handle disconect');
        delete globalUsers[socket.id];
        console.log(globalUsers);
    });
});

/* Redis */
subscriber.on('message', (channel, message) => {
   console.log(channel, message);
});
subscriber.subscribe('update-image', 'update-chat', 'ban-chat');