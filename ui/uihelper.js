// skin.js may redefine these jQuery functionss if necessary

jQuery.fn.menuReveal = function(callback) {
    if (callback) {
        this.slideToggle('fast',callback);
    } else {
        this.slideToggle('fast');
    }
    return this;
}

jQuery.fn.menuHide = function(callback) {
    if (callback) {
        this.slideToggle('fast',callback);
    } else {
        this.slideToggle('fast');
    }
    return this;

}

jQuery.fn.isOpen = function() {
    return this.hasClass('icon-toggle-open');
}

jQuery.fn.isClosed = function() {
    return this.hasClass('icon-toggle-closed');
}

jQuery.fn.toggleOpen = function() {
    if (this.hasClass('icon-toggle-closed')) {
        this.removeClass('icon-toggle-closed').addClass('icon-toggle-open');
    }
    return this;
}

jQuery.fn.toggleClosed = function() {
    if (this.hasClass('icon-toggle-open')) {
        this.removeClass('icon-toggle-open').addClass('icon-toggle-closed');
    }
    return this;
}

jQuery.fn.makeSpinner = function() {

    return this.each(function() {
        var originalclasses = new Array();
        var classes = '';
        if ($(this).attr("class")) {
            var classes = $(this).attr("class").split(/\s/);
        }
        for (var i = 0, len = classes.length; i < len; i++) {
            if (classes[i] == "invisible" || (/^icon/.test(classes[i]))) {
                originalclasses.push(classes[i]);
                $(this).removeClass(classes[i]);
            }
        }
        $(this).attr("originalclass", originalclasses.join(" "));
        $(this).addClass('icon-spin6 spinner');
    });
}

jQuery.fn.stopSpinner = function() {
    return this.each(function() {
        $(this).removeClass('icon-spin6 spinner');
        if ($(this).attr("originalclass")) {
            $(this).addClass($(this).attr("originalclass"));
            $(this).removeAttr("originalclass");
        }
    });
}

jQuery.fn.makeTagMenu = function(options) {
    var settings = $.extend({
        textboxname: "",
        textboxextraclass: "",
        labelhtml: "",
        populatefunction: null,
        buttontext: null,
        buttonfunc: null,
        buttonclass: ""
    },options);

    this.each(function() {
        var tbc = "enter combobox-entry";
        if (settings.textboxextraclass) {
            tbc = tbc + " "+settings.textboxextraclass;
        }
        $(this).append(settings.labelhtml);
        var holder = $('<div>', { class: "expand"}).appendTo($(this));
        var textbox = $('<input>', { type: "text", class: tbc, name: settings.textboxname }).
            appendTo(holder);
        var dropbox = $('<div>', {class: "drop-box tagmenu dropshadow"}).appendTo(holder);
        var menucontents = $('<div>', {class: "tagmenu-contents"}).appendTo(dropbox);
        if (settings.buttontext !== null) {
            var submitbutton = $('<button>', {class: "fixed"+settings.buttonclass,
                style: "margin-left: 8px"}).appendTo($(this));
            submitbutton.html(settings.buttontext);
            if (settings.buttonfunc) {
                submitbutton.click(function() {
                    settings.buttonfunc(textbox.val());
                });
            }
        }

        dropbox.mCustomScrollbar({
        theme: "light-thick",
        scrollInertia: 120,
        contentTouchScroll: 25,
        advanced: {
            updateOnContentResize: true,
            updateOnImageLoad: false,
            autoScrollOnFocus: false,
            autoUpdateTimeout: 500,
        }
        });
        textbox.hover(makeHoverWork);
        textbox.mousemove(makeHoverWork);
        textbox.click(function(ev) {
            ev.preventDefault();
            ev.stopPropagation();
            var position = getPosition(ev);
            var elemright = textbox.width() + textbox.offset().left;
            if (position.x > elemright - 24) {
                if (dropbox.is(':visible')) {
                    dropbox.slideToggle('fast');
                } else {
                    var data = settings.populatefunction(function(data) {
                        menucontents.empty();
                        for (var i in data) {
                            var d = $('<div>', {class: "backhi"}).appendTo(menucontents);
                            d.html(data[i]);
                            d.click(function() {
                                var cv = textbox.val();
                                if (cv != "") {
                                    cv += ",";
                                }
                                cv += $(this).html();
                                textbox.val(cv);
                            });
                        }
                        dropbox.slideToggle('fast', function() {
                            dropbox.mCustomScrollbar("update");
                        });
                    });
                }
            }
        });
    });
}

jQuery.fn.fanoogleMenus = function() {
    return this.each( function() {
        var top = $(this).children().first().children('.mCSB_container').offset().top;
        var conheight = $(this).children().first().children('.mCSB_container').height();
        var ws = getWindowSize();
        var avheight = ws.y - top;
        var nh = Math.min(avheight, conheight);
        $(this).css({height: nh+"px", "max-height":''});
        $(this).mCustomScrollbar("update");
        if ($(this).attr("id") == "hpscr") {
            $(this).mCustomScrollbar("scrollTo", '.current', {scrollInertia:0});
        }
    });
}

// Functions that could just be in layoutProcessor, but it makes maintenance easier
// if we have a proxy like this so we don't have to add new stuff to every single skin.

var uiHelper = function() {

    return {
    
        findAlbumDisplayer: function(key) {
            try {
                return layoutProcessor.findAlbumDisplayer(key);
            } catch (err) {
                // For finding where to insert nre album headers
                // The key is the id of a dropdown div.
                if ($("#"+key).length > 0) {
                    // If it already exists
                    return $("#"+key);
                } else {
                    // Opener div (standard UI)
                    return $('i[name="'+key+'"]').parent();
                }
            }
        },
        
        findArtistDisplayer: function(key) {
            try {
                return layoutProcessor.findArtistDisplayer(key);
            } catch (err) {
                if ($("#"+key).length > 0) {
                    // If it already exists
                    return $("#"+key);
                } else {
                    // Opener div (standard UI)
                    return $('i[name="'+key+'"]').parent();
                }
            }
        },
        
        insertAlbum: function(v) {
            try {
                return layoutProcessor.insertAlbum(v);
            } catch (err) {
                var albumindex = v.id;
                $('#aalbum'+albumindex).html(v.tracklist);
                var dropdown = $('#aalbum'+albumindex).is(':visible');
                uiHelper.findAlbumDisplayer('aalbum'+albumindex).remove();
                switch (v.type) {
                    case 'insertAfter':
                        debug.log("Insert After",v.where);
                        $(v.html).insertAfter(uiHelper.findAlbumDisplayer(v.where));
                        break;
            
                    case 'insertAtStart':
                        debug.log("Insert At Start",v.where);
                        $(v.html).prependTo($('#'+v.where));
                        break;
                }
                if (dropdown) {
                    uiHelper.findAlbumDisplayer('aalbum'+albumindex).find('.menu').click();
                }
                layoutProcessor.postAlbumActions();
            }
        },
        
        insertArtist: function(v) {
            try {
                return layoutProcessor.insertArtist(v);
            } catch(err) {
                switch (v.type) {
                    case 'insertAfter':
                        debug.log("Insert After",v.where);
                        switch (prefs.sortcollectionby) {
                            case 'album':
                            case 'albumbyartist':
                                $(v.html).insertAfter(uiHelper.findAlbumDisplayer(v.where));
                                break;
                                
                            case 'artist':
                                $(v.html).insertAfter(uiHelper.findArtistDisplayer(v.where));
                                break;
                        }
                        break;
            
                    case 'insertAtStart':
                        debug.log("Insert At Start",v.where);
                        $(v.html).prependTo($('#'+v.where));
                        break;
                }
            }
        },
        
        removeAlbum: function(key) {
            try {
                return layoutProcessor.removeAlbum(key);
            } catch (err) {
                $('#'+key).remove();
                uiHelper.findAlbumDisplayer(key).remove();
                layoutProcessor.postAlbumActions();
            }
        },

        removeArtist: function(v) {
            try {
                return layoutProcessor.removeArtist(v);
            } catch (err) {
                $("#aartist"+v).remove();
                uiHelper.findArtistDisplayer('aartist'+v).remove();
                layoutProcessor.postAlbumActions();
            }
        },

        setupPersonalRadio: function(key) {
            try {
                return layoutProcessor.setupPersonalRadio(key);
            } catch (err) {

            }
        },

        setupPersonalRadioAdditions: function(key) {
            try {
                return layoutProcessor.setupPersonalRadioAdditions(key);
            } catch (err) {

            }
        },
        
        emptySearchResults: function() {
            try {
                return layoutProcessor.emptySearchResults();
            } catch (err) {
                $('#searchresultholder').empty();
            }
        },
        
        fixupArtistDiv(jq, name) {
            try {
                return layoutProcessor.fixupArtistDiv(jq, name);
            } catch (err) {
                
            }
        }
    
    }

}();