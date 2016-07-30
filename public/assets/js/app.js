function Interval(a, b) {
    var c = false;
    this.start = function() {
        if (!this.isRunning()) {
            c = setInterval(a, b);
        }
    };
    this.stop = function() {
        clearInterval(c);
        c = false;
    };
    this.isRunning = function() {
        return c !== false;
    };
}
function cancelEvent(e) {
    e.preventDefault();
    e.stopPropagation();
}

function getRank(rank){
    return $.grep($config.rangos, function(b) {
        return b.id == rank;
    })[0];
}

function handleMessage(message) {
    if(!$config.ready) return;
    if (message.user != $config.lastUser) {
        $config.lastUser = message.user;
        message.rank = getRank(message.rank)['name'];
        $chatbox.append($messageTemplate(message));
        $utils.messages++;
    } else {
        $(".mensaje").last().append($messageChildTemplate(message));
    }
}
function getCurrentDate(){
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


function chatboxResponsive(){
    $('.chatbox').height($(window).height() - $('#messageBox').height()*2 +1);
}

var socket = io.connect('/');
var $messageTemplate = Handlebars.compile($('#messageTemplate').html());
var $messageChildTemplate = Handlebars.compile($('#messageChildTemplate').html());
var $usersTemplate = Handlebars.compile($('#usersTemplate').html());
var $chatbox = $('.chatbox');
var sendReady = true;
var $config = {
    ready: false,
    user: null,
    lastUser: null,
    smilies: null,
    rangos: null
};
var $utils = {
    messages: 0,
    saveConfig: true,
    smiliesShown: 0,
    smiliesLock: false
};
var $chat = {
    last: new Date().getTime(),
    title: document.title,
    focus: true,
    counter: 0,
    interval: null,
    seed: Math.floor(Math.random() * (20 - 30 + 1)) + 20
};
/* Get client cache */
$.getJSON('cache/client.json', function(data){
    $config.rangos = [];
    $.each(data.ranks, function(i, v){
        console.log(v);
        $config.rangos.push({
            'id': v.id,
            'name': v.name,
            'permission': JSON.parse(v.chatPermissions)
        });
    });
    $config.ready = true;
});
/* Chat Events */
socket.on('message', function(user){
    console.log("Called", user);
    user.time = getCurrentDate();
    handleMessage(user);
});
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
    setTimeout(function() {
        sendReady = true;
    }, 1000);
    $msgInput.val('');
})
$(window).on("resize", function() {
    chatboxResponsive();
});
$(document).ready(function() {
    chatboxResponsive();
});

