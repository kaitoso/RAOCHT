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
function handleMessage(a) {
    if (a.user != $config.lastUser) {
        $config.lastUser = a.user;
        $chatbox.append($messageTemplate(a));
        $utils.messages++;
    } else {
        $(".mensaje").last().append($messageChildTemplate(a));
    }
}
function getCurrentDate(){
    return new Date().toLocaleDateString(
        'es-419',
        {
            weekday: 'long',
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

