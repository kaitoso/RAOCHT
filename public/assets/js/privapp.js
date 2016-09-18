var $userTemplate = Handlebars.compile($('#userTemplate').html());
var $user = new Bloodhound({
    datumTokenizer: Bloodhound.tokenizers.obj.whitespace('value'),
    queryTokenizer: Bloodhound.tokenizers.whitespace,
    remote: {
        wildcard: '%QUERY',
        url: $baseUrl+'/perfil/search/%QUERY'
    }
});
$user.initialize();

$('#inputUser').typeahead({
    minLength: 3
}, {
    display: 'user',
    limit: 10,
    source: $user.ttAdapter(),
    templates: {
        empty: [
            '<div class="empty-message">',
            'No se encontró a ningún usuario con este nombre.',
            '</div>'
        ].join('\n'),
        suggestion: $userTemplate
    }
});
Handlebars.registerHelper("unescape", function (a) {
    return new Handlebars.SafeString(a);
});
function array_flip(trans) {
    var key, tmp_ar = {};
    for (key in trans) {
        if (trans.hasOwnProperty(key)) {
            tmp_ar[trans[key]] = key;
        }
    }
    return tmp_ar;
}
function Interval(a, b) {
    var c = false;
    this.start = function () {
        if (!this.isRunning()) {
            c = setInterval(a, b);
        }
    };
    this.stop = function () {
        clearInterval(c);
        c = false;
    };
    this.isRunning = function () {
        return c !== false;
    };
}
function cancelEvent(e) {
    e.preventDefault();
    e.stopPropagation();
}

function getRank(rank) {
    return $.grep($config.rangos, function (b) {
        return b.id == rank;
    })[0];
}
function chatboxResponsive() {
    $('.chatbox').height($(window).height() - $('#messageBox').height() * 2 - 50);
    $('#sideUsers').height($(window).height() - $('#messageBox').height() * 2 - 10);
}

function getMessageUsers(){
    $.getJSON($baseUrl+'/private/messages', function(data){
        if(!data.length){
            return;
        }
        $('#contactList').html('');
        var fromUser = null;
        $.each(data, function(i, v){
            if($userID === v.id){
                fromUser = {
                    id: v.to_id,
                    user: v.to_user,
                    image: $baseUrl + '/avatar/s/' + v.to_image,
                    chatColor: v.to_color,
                    chatText: v.to_text,
                    message: 'Tu: ' + v.message,
                    send_date: v.send_date,
                    time: moment.unix(v.send_date).fromNow(),
                    seen: 1
                }
            }else{
                fromUser = v;
                fromUser.time = moment.unix(v.send_date).fromNow();
                fromUser.image = $baseUrl + '/avatar/s/' + v.image;
            }
            $('#contactList').append($mainContactTemplate(fromUser));
        });
    });
}
function getUserMessages(id){
    if($userID == currentLocation){
        return;
    }
    swal({
        title: "Cargando mensajes...",
        text: "Espere un momento mientras se cargan los mensajes...",
        imageUrl: $baseUrl + "/assets/img/loading_spin.gif",
        showConfirmButton: false
    });
    $.getJSON($baseUrl+'/private/messages/user/' + id, function(data){
        if(!data.length){
            $chatbox.html('¡Se el primero en enviarle un mensaje!');
            swal.close();
            return;
        }
        $chatbox.html('');
        $.each(data.reverse(), function(i, v){
            v.originalTime = v.send_date;
            v.time = moment.unix(v.send_date).fromNow();
            v.image = $baseUrl + '/avatar/s/' + v.image;
            handleMessage(v);
        });
        chatBottom();
        $('#user-'+id+' a').removeClass('notseen');
        swal.close();
    });
}
function getCache() {
    $.getJSON('cache/client.json?time' +  new Date().getTime(), function (data) {
        $config.rangos = [];
        $config.smilies = data.smilies;
        $config.autocomplete[0] = [];
        $.each(data.ranks, function (i, v) {
            $config.autocomplete[0].push(v.name);
            $config.rangos.push({
                'id': v.id,
                'name': v.name,
                'permission': JSON.parse(v.chatPermissions)
            });
        });
        $.each($config.smilies, function(i, v){
            $config.autocomplete[0].push(':' + v.code + ':');
        });
        if(!$config.ready){
            socket.emit('ready', {ready: true});
            $config.ready = true;
        }
    });
}

function linkifyChat(str) {
    var urlRegex = /(\b(https?):\/\/[-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#\/%=~_|])/ig;
    var imageRegex = /\b(https?:\/\/\S+(?:png|jpe?g|gif)\S*)\b/;
    var audioRegex = /\b(https?:\/\/\S+(?:mp3|ogg)\S*)\b/;
    var videoRegex = /\b(https?:\/\/\S+(?:mp4|webm|ogv)\S*)\b/;
    return str.replace(urlRegex, function (match) {
        if (imageRegex.test(match)) {
            return '<a href="' + match + '" target="_blank"><span class="smilie"><img src="' + match + '"/></span></a>';
        }
        if (audioRegex.test(match)) {
            return '<audio controls><source src="' + match + '">Tu navegador no soporta la etiqueta "Audio"</audio>';
        }
        if (videoRegex.test(match)) {
            return '<video class="srcVideo" src="' + match + '" preload controls></video>'
        }

        return '<a href="' + match + '" target="_blank">' + match + '</a>';
    });
}
function smilies(str) {
    var limit = 0;
    $.each($config.smilies, function (i, val) {
        var currentSmilie = val;
        var d = new RegExp(':' + currentSmilie.code + ':', "g");
        if (limit > 3) {
            return false;
        }
        str = str.replace(d, function (match) {
            limit++;
            if (limit > 3) {
                return val.code;
            }
            var url = currentSmilie.url;
            if (currentSmilie.local) {
                url = $baseUrl + '/smilies/' + currentSmilie.url;
            }
            return '<span class="smilie"><img src="' + url + '" title="' + currentSmilie.code + '"/></span>';
        });
    });
    return str;
}
function chatBottom() {
    $chatbox.scrollTop($chatbox[0].scrollHeight + 480);
}
function handleMessage(message) {
    if (!$config.ready) return;
    var rank = getRank(message.rank);
    if(message.id !== $userID){
        $('#user-'+message.id+' .timeAgo').text(' ' +moment.unix(message.originalTime).fromNow())
        $('#user-'+message.id+' .timeAgo').data('time', message.originalTime)
        $('#user-'+ message.id +' p').html(message.message);
    }else{
        $('#user-'+currentLocation+' .timeAgo').text(' ' +moment.unix(message.originalTime).fromNow())
        $('#user-'+currentLocation+' .timeAgo').data('time', message.originalTime)
        $('#user-'+ currentLocation +' p').html('Tu: ' + message.message);
    }
    message.message = linkifyChat(message.message, rank.permission);
    message.message = smilies(message.message);
    if (message.user != $config.lastUser) {
        $config.lastUser = message.user;
        message.rank = rank['name'];
        $chatbox.append($messageTemplate(message));
    } else {
        $(".contenido").last().find('.timeAgo').text(' ' +moment.unix(message.originalTime).fromNow());
        $(".contenido").last().append($messageChildTemplate(message));
    }
    if (!$chat.focus) {
        $chat.counter++;
        if (!($chat.interval instanceof Interval)) {
            $chat.interval = new Interval(function () {
                $("title").text(($("title").text() === $chat.title) ? "Nuevo mensaje (" + $chat.counter + ")" : $chat.title);
            }, 2000);
        }
        if (!$chat.interval.isRunning()) {
            $chat.interval.start();
        }
    }
    if (($chatbox.scrollTop() + $(document).height()) >= $chatbox[0].scrollHeight - 480) {
        chatBottom();
    }
}
var $config = {
    ready: false,
    user: null,
    lastUser: null,
    smilies: null,
    rangos: null,
    autocomplete: []
};
var $chat = {
    last: new Date().getTime(),
    title: document.title,
    focus: true,
    counter: 0,
    interval: null,
    seed: Math.floor(Math.random() * (20 - 30 + 1)) + 20
};
var socket = io.connect('http://chat.animeobsesion.net:8080/privado');
var $mainContactTemplate = Handlebars.compile($('#mainContactTemplate').html());
var $messageTemplate = Handlebars.compile($('#messageTemplate').html());
var $messageChildTemplate = Handlebars.compile($('#messageChildTemplate').html());
var $chatbox = $('.chatbox');
var sendReady = true;
var currentLocation = location.hash.slice(1);
/* Socket events */
socket.on('message', function (user) {
    if(currentLocation == user.id || user.id === $userID){
        user.originalTime = moment().unix();
        user.time = moment().fromNow();
        handleMessage(user);
    }else{
        $('#user-'+ user.id).insertBefore($('.sidemenu .left').first());
        user.send_date = moment().unix();
        user.time = moment().fromNow();
        user.seen = 0;
        $('#user-'+user.id).remove();
        $('#contactList').prepend($mainContactTemplate(user));
    }
    if (!$chat.focus) {
        $chat.counter++;
        if (!($chat.interval instanceof Interval)) {
            $chat.interval = new Interval(function () {
                $("title").text(($("title").text() === $chat.title) ? "Nuevo mensaje (" + $chat.counter + ")" : $chat.title);
            }, 2000);
        }
        if (!$chat.interval.isRunning()) {
            $chat.interval.start();
        }
    }
});
socket.on('restart', function () {
    console.log('Go restart');
    setTimeout(function() { window.location.reload(); }, 1000);
});
socket.on('client-update', function (message) {
    getCache();
});
socket.on('global', function (message) {
    $('#globalUser').text('¡' + message.user + ' ha enviado un mensaje global!');
    $('#globalMessage').html(linkifyGlobal(message.message));
    $('#modalGlobal').modal('show');
});

/* Page events */
$('#messageBox').submit(function (e) {
    cancelEvent(e);
    var $msgInput = $("#messageBox input");
    if (!$msgInput.val() || !sendReady) {
        return false;
    }
    if(currentLocation === '') return false;
    socket.emit('message', {
        to: parseInt(currentLocation),
        message: $msgInput.val()
    });
    sendReady = false;
    setTimeout(function () {
        sendReady = true;
    }, 1000);
    $msgInput.val('');
});
$(window).focus(function () {
    if ($chat.interval instanceof Interval) {
        $chat.interval.stop();
    }
    $chat.focus = true;
    $chat.counter = 0;
    $("title").text($chat.title);
});
$(window).blur(function () {
    $chat.focus = false;
});
$(window).on('hashchange', function() {
    $('#user-'+currentLocation+' a').removeClass('cursor');
    currentLocation = location.hash.slice(1);
    getUserMessages(currentLocation);
    $('#user-'+currentLocation+' a').addClass('cursor');
});
$(window).on("resize", function () {
    chatboxResponsive();
    chatBottom();
});
$(document).ready(function(){
   if(currentLocation !== ""){
       getUserMessages(currentLocation);
   }
    $('#messageBox input').tabComplete($config.autocomplete);
});
setInterval(function(){
    $('.timeAgo').each(function(i, v){
        var time = $(v).data('time');
        $(v).text(' ' +moment.unix(time).fromNow());
    });
}, 60000)
getCache();
getMessageUsers();
chatboxResponsive();
