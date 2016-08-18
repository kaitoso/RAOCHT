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

function linkifyChat(str, permissions) {
    var urlRegex = /(\b(https?):\/\/[-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#\/%=~_|])/ig;
    var imageRegex = /\b(https?:\/\/\S+(?:png|jpe?g|gif)\S*)\b/;
    var audioRegex = /\b(https?:\/\/\S+(?:mp3|ogg)\S*)\b/;
    var videoRegex = /\b(https?:\/\/\S+(?:mp4|webm|ogv)\S*)\b/;
    var perms = array_flip(permissions);
    return str.replace(urlRegex, function (match) {
        if (imageRegex.test(match) && perms.images != null) {
            return '<a href="' + match + '" target="_blank"><span class="smilie"><img src="' + match + '"/></span></a>';
        }
        if (audioRegex.test(match) && perms.audio != null) {
            return '<audio controls><source src="' + match + '">Tu navegador no soporta la etiqueta "Audio"</audio>';
        }
        if (videoRegex.test(match) && perms.videos != null) {
            return '<a data-toggle="modal" href="#modalvideo" class="openVideo" data-video="' + match + '">' + match + '</a>'
        }

        return '<a href="' + match + '" target="_blank">' + match + '</a>';
    });
}

function linkifyGlobal(str) {
    var urlRegex = /(\b(https?):\/\/[-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#\/%=~_|])/ig;
    var imageRegex = /\b(https?:\/\/\S+(?:png|jpe?g|gif)\S*)\b/;
    var audioRegex = /\b(https?:\/\/\S+(?:mp3|ogg)\S*)\b/;
    var videoRegex = /\b(https?:\/\/\S+(?:mp4|webm|ogv)\S*)\b/;
    return str.replace(urlRegex, function (match) {
        if (imageRegex.test(match)) {
            return '<a href="' + match + '" target="_blank"><img src="' + match + '" title="' + match + '" class="img-responsive"></a>';
        }
        if (audioRegex.test(match)) {
            return '<audio autoplay controls><source src="' + match + '">Tu navegador no soporta la etiqueta "Audio"</audio>';
        }
        if (videoRegex.test(match)) {
            return $videoTemplate({video: match});
        }

        return '<a href="' + match + '" target="_blank">' + match + '</a>';
    });
}
function chatBottom() {
    $chatbox.scrollTop($chatbox[0].scrollHeight + 480);
}
function handleMessage(message) {
    if (!$config.ready) return;
    var rank = getRank(message.rank);
    message.message = linkifyChat(message.message, rank.permission);
    message.message = smilies(message.message);
    if (message.user != $config.lastUser) {
        $config.lastUser = message.user;
        message.rank = rank['name'];
        $chatbox.append($messageTemplate(message));
        $utils.messages++;
    } else {
        $(".contenido").last().append($messageChildTemplate(message));
    }
    if (($chatbox.scrollTop() + $(document).height()) >= $chatbox[0].scrollHeight - 480) {
        chatBottom();
    }
}
function getCurrentDate() {
    return new Date().toLocaleDateString(
        'es-419',
        {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: 'numeric',
            minute: 'numeric',
            second: 'numeric'
        }
    );
}

/* Get client cache */
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
            $chat.check = new Interval(function() {
                var rankid = $('#controlRank').data('rank');
                var perms = array_flip(getRank(rankid).permission);
                if(perms.nokick != null){
                    $chat.check.stop();
                    return;
                }
                var currentTime = new Date().getTime();
                if (currentTime > $chat.last + ($chat.seed * 1000 * 60)) {
                    $chat.check.stop();
                    socket.disconnect();
                    $.getJSON($baseUrl+'/logout', function(data){
                        console.log(data);
                    });
                    swal({
                        title: '¡Te han pateado!',
                        text: "Por la razón de: estuviste más de " + $chat.seed + " minutos sin enviar ningún mensaje",
                        type: 'warning',
                        showCancelButton: false,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: '¡Ya que!'
                    }).then(function() {
                        window.location.href=$baseUrl+'/login';
                    });
                }
            }, 30000);
            $chat.check.start();
        }
    });
}

function getLogros(id) {
    var logQueue = [];
    var url = $baseUrl+ '/cuenta/logros.json';
    if(id > 0){
        url+='/'+id;
    }
    $.getJSON(url, function (data) {
        $.each(data, function (i, v) {
            logQueue.push({
                title: '¡Nuevo logro desbloqueado!',
                html: $logroTemplate(v),
                showCancelButton: false,
                confirmButtonColor: '#DD6B55',
                confirmButtonText: '¡Genial!',
            })
        });
        swal.queue(logQueue);
    });
}


function chatboxResponsive() {
    $('.chatbox').height($(window).height() - $('#messageBox').height() * 2 + 1);
}

var socket = io.connect('/chat');
var $messageTemplate = Handlebars.compile($('#messageTemplate').html());
var $messageChildTemplate = Handlebars.compile($('#messageChildTemplate').html());
var $usersTemplate = Handlebars.compile($('#usersTemplate').html());
var $chatbox = $('.chatbox');
var $videoTemplate = Handlebars.compile($('#videoTemplate').html());
var $logroTemplate = Handlebars.compile($('#logroTemplate').html());
var $smiliesTemplate = Handlebars.compile($('#smiliesTemplate').html());
var sendReady = true;
var $config = {
    ready: false,
    user: null,
    lastUser: null,
    smilies: null,
    rangos: null,
    autocomplete: []
};
var $utils = {
    messages: 0,
    saveConfig: true,
    smiliesShown: 0,
    smiliesLock: false
};
var $system = {
    user: 'Sistema',
    chatName: 'Sistema',
    chatText: 'f50c0c',
    chatColor: 'f50c0c',
    image: $baseUrl+'/avatar/sys.png',
    rank: 1,
}
var $chat = {
    last: new Date().getTime(),
    title: document.title,
    focus: true,
    counter: 0,
    interval: null,
    privates: 0,
    seed: Math.floor(Math.random()*(25-20+1)+20),
    check: null
};
/* Chat Events */
socket.on('message', function (user) {
    user.time = getCurrentDate();
    handleMessage(user);
});

socket.on('privado', function(user){
    alertify.log('<strong>'+ user.user + '</strong> te ha envíado un mensaje!<br> ' + user.message, "", 0);
    $chat.privates++;
    $('#pvNumber').text($chat.privates);
});

socket.on('system', function(message){
    $system.time = getCurrentDate();
    $system.message = message.message;
    $system.rank = 1;
    handleMessage($system);
});

socket.on('update', function (user) {
    var userRank = getRank(user.rank);
    var perms = array_flip(userRank.permission);
    $('#controlUser').text(user.user);
    $('#controlRank').text(userRank.name);
    $('#controlRank').data('rank', user.rank);
    $('#controlImage').attr('src', user.image);
    if(userRank.permission.length > 0){
        $('#adminView').show();
    }else{
        $('#adminView').hide();
    }
    if(perms.nokick === undefined) {
        $chat.check.start();
    }
});

socket.on('global', function (message) {
    $('#globalUser').text('¡' + message.user + ' ha enviado un mensaje global!');
    $('#globalMessage').html(linkifyGlobal(message.message));
    $('#modalGlobal').modal('show');
});

socket.on('background', function (message) {
    console.log(message);
    if(message.background != null){
        $("#mainContainer").css('background', "url('" + message.background + "') no-repeat scroll left center / cover");
    }else{
        $("#mainContainer").css('background','none');
    }
    if(message.side != null){
        $("#myNavmenu").css('background', "rgba(0, 0, 0, 0) url('" + message.side + "') no-repeat scroll left center / cover");
    }else{
        $("#myNavmenu").css('background','rgba(0, 0, 0, 0.87)');
    }
});

socket.on('achievement', function (message) {
    getLogros(message.id);
});

socket.on('client-update', function (message) {
    getCache();
});

socket.on('online', function(users){
    $('#chatUsuarios').html('');
    $config.autocomplete[1] = [];
    $.each(users, function(index, val){
        $config.autocomplete[1].push(val.user);
        $('#chatUsuarios').append($usersTemplate(val));
    });
    $('#onlineCount').text(users.length);
});

socket.on('restart', function () {
    console.log('Go restart');
    setTimeout(function() { window.location.reload(); }, 1000);
});

socket.on('offline', function () {
    $chat.check.stop();
    socket.disconnect();
    window.location.href=$baseUrl+'/login';
});

socket.on('kick', function (message) {
    $chat.check.stop();
    socket.disconnect();
    $.getJSON($baseUrl+'/logout', function(data){
       console.log(data);
    });
    swal({
        title: '¡Te han pateado!',
        text: "Por la razón de: " + message.reason,
        type: 'warning',
        showCancelButton: false,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: '¡Ya que!'
    }).then(function() {
        window.location.href=$baseUrl+'/login';
    });
});

socket.on('activity', function(){
    $chat.last = new Date().getTime();
});

socket.on("disconnect", function () {
    console.log('Desconectado');
    /*swal("Lo sentimos...", "Hubo un error con la conexión del chat. Quizá esté en mantenimiento. Intenta mas tarde o actualiza la ventana del chat.", "error");*/
});

/* Page events */
$('#messageBox').submit(function (e) {
    cancelEvent(e);
    var $msgInput = $("#messageBox input");
    if (!$msgInput.val() || !sendReady) {
        return false;
    }
    socket.emit('message', {
        message: $msgInput.val()
    });
    sendReady = false;
    setTimeout(function () {
        sendReady = true;
    }, 1000);
    $chat.last = new Date().getTime();
    $msgInput.val('');
});
$('.chatbox').on('click', '.openVideo', function (event) {
    event.preventDefault();
    var videoUrl = $(this).data('video');
    var video = $('#videoModal');
    video.attr('src', videoUrl);
    video[0].play();
});
$('#modalGlobal').on('hidden.bs.modal', function () {
    $('#globalMessage').html('');
});
$('#openSmilies').on('click', function (event) {
    event.preventDefault();
    $("#modalSmilies").modal('show');
});

$("#modalSmilies").on("show.bs.modal", function(event) {
    $("#bodySmilies").empty();
    $utils.smiliesShown = 30;
    var limit = $config.smilies.length;
    if(limit/30 < 1){
        $utils.smiliesLock = true;
    }else{
        limit = 30;
    }
    for (var a = 0; a < limit; a++) {
        $("#bodySmilies").append($smiliesTemplate($config.smilies[a]));
    }
});
$("#modal-smilies").scroll(function(e) {
    if ($("#modalSmilies .modal-dialog").height() >= $("#modalSmilies").scrollTop() + $(document).height() || $utils.smiliesLock) {
        return;
    }
    var d = $config.smilies.length;
    if ($utils.smiliesShown == d) {
        $utils.smiliesLock = true;
        return;
    }
    var c = d - $utils.smiliesShown;
    var f = c >= 20 ? 20 : c;
    for (var b = $utils.smiliesShown, a = f; a > 0; b++, a--) {
        $("#bodySmilies").append(smilies_template($config.smilies[b]));
    }
    $utils.smiliesShown += f;
});
$('#btnPrivate').on('click', function(){
    $chat.privates = 0;
    $('#pvNumber').text($chat.privates);
});
$("#bodySmilies").on("click", ".smilieTemplate", function(event) {
    cancelEvent(event);
    $('#messageBox input').val($('#messageBox input').val() + $(this).data("code"));
    var that = $('#messageBox input');
    setImmediate(function(){
        that.selectionStart = that.selectionEnd = 256;
    });
    $("#modalSmilies").modal("hide");
    $('#messageBox input').focus();
});
$(window).on("resize", function () {
    chatboxResponsive();
    chatBottom();
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
$(document).ready(function () {
    chatboxResponsive();
    getCache();
    getLogros(0);
    $('#messageBox input').tabComplete($config.autocomplete);
});
$('body').on('swipe', function() {
    if($(document).width() < 958){
        $('.navmenu').offcanvas('toggle');
    }
});