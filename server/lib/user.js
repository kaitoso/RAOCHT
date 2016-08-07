var config = require('./config');
var mysql = require('mysql');
var pool  = mysql.createPool(config.db);

var user = {};


user.updateData = function(user){
    pool.query(`UPDATE user_profiles SET online_time = online_time + ${user.logTime}, messages = messages + ${user.messages} WHERE user_id = ${user.id}` , function(err, result) {
        if (err){
            console.error(err);
            return;
        };
        console.log(`Updating user ${user.user}. Set Logtime ${user.logTime}; Messages ${user.messages}`, 'Results', result);
    });
};



module.exports = user;