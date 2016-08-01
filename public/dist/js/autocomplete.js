/*!
 * jquery-tab-complete
 * https://github.com/erming/jquery-tab-complete
 *
 * Copyright (c) 2014 Mattias Erming <mattias@mattiaserming.com>
 * Licensed under the MIT License.
 *
 * Version 0.2.0
 */

(function($) {
    $.fn.tabComplete = function(list, options) {
        var settings = $.extend({
            after: '',
            caseSensitive: false,
        }, options);

        var self = this;
        var currentList = list;
        if (self.size() > 1) {
            return self.each(function() {
                $(this).tabComplete(list, options);
            });
        }

        var match = [];
        self.unbind('keydown');
        self.on('keydown', function(e) {
            var key = e.which;
            if (key != 9) {
                match = [];
                return;
            }

            var text = self.val().trim().split(' ');
            var last = text.splice(-1)[0];

            if (!match.length) {
                $.each(currentList,function (i, v){
                    console.log(i, v);
                    if (currentList[i] === undefined)
                        return true;
                    match = $.grep(currentList[i], function(w) {
                        var l = last;
                        if (l == '') {
                            return;
                        }
                        if (!settings.caseSensitive) {
                            l = l.toLowerCase();
                            w = w.toLowerCase();
                        }
                        return w.indexOf(l) !== -1;
                    });
                    if(match.length > 0){
                        return false;
                    }
                });
            }

            var i = match.indexOf(last) + 1;
            if (i == match.length) {
                i = 0;
            }

            if (match.length) {
                text.push(match[i]);
                self.val(text.join(' ') + settings.after);
            }

            return false;
        });
    };
})(jQuery);