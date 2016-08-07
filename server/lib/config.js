var config = {};

config.db = {};
config.db.connectionLimit = 10;
config.db.host = 'localhost';
config.db.user = 'rao';
config.db.password = 'Xu2iXI7i14RA';
config.db.database = 'rao_chat';

config.redis = {};
config.redis.host = '127.0.0.1';
config.redis.port = 6379;

config.app = {};
config.app.port = 8080;

module.exports = config;