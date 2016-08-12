"use strict";
var radio = require('node-internet-radio');
var stream  = {
    title: '',
    announcer: '',
    url: ''
};

stream.getStreamData = function(io) {
    radio.getStationInfo('http://animeobsesion.net:8000', (error, station) => {
        if(error){
            console.error('Error on station: ', error);
            return;
        }
        let oldData = {
            title: this.title,
            announcer: this.announcer,
            url: this.url
        };
        this.title = station.title;
        this.announcer = station.headers['icy-name'];
        this.url = station.headers['icy-url'];
        if(this.announcer !== oldData.announcer){
            io.emit('system', {
                message: '¡Ahora locuta ' + this.announcer + '!'
            });
        }
    }, radio.StreamSource.STREAM);
}

stream.getData = function(){
    return {
        title: this.title,
        announcer: this.announcer,
        url: this.url
    }
}


module.exports = stream;