"use strict";
var radio = require('node-internet-radio');
var stream  = {
    title: '',
    announcer: '',
    url: ''
};

stream.getStreamData = function(io) {
    let that = this;
    process.nextTick(function() {
        radio.getStationInfo('http://animeobsesion.net:8000', (error, station) => {
            if(error){
                console.error('Error on station: ', error);
                return;
            }
            let oldData = {
                title: that.title,
                announcer: that.announcer,
                url: that.url
            };
            that.title = station.title;
            that.announcer = station.headers['icy-name'];
            that.url = station.headers['icy-url'];
            if(that.announcer !== oldData.announcer){
                io.emit('system', {
                    message: '¡Ahora locuta ' + that.announcer + '!'
                });
            }
        }, radio.StreamSource.STREAM);
    });
}

stream.getData = function(){
    return {
        title: this.title,
        announcer: this.announcer,
        url: this.url
    }
}


module.exports = stream;