;(function($, undefined){
var globalClickHandler = function(e){
   // console.log('global click', e);
    
};
var globalMousedownHandler = function(e){
    console.log('global mousedown', e);
    // any mousedown outside of list forces finishRename
    if (activeExplorer !== null){
        activeExplorer.finishRename();
    }
    deactivateExplorers();
};


var keydownHandler = function(e){
    
    var key = keyString(e);
    console.log('keydown', key, e);
    
    
    if (activeExplorer === null){
        return true; // skip events outside or while renaming
    }
    if (key === 'ENTER'){
        e.preventDefault();
        activeExplorer.finishRename();
        return false;
    }
    if (renaming){
        return true;
    }
    var methods = {
        'LEFT': 'navigateLeft',
        'UP': 'navigateUp',
        'RIGHT': 'navigateRight',
        'DOWN': 'navigateDown',
        'HOME': 'navigateHome',
        'END': 'navigateEnd',
        'BACKSPACE': 'navigateParent',
        'ENTER': 'navigateSelected',
        'F2': 'startRename',
        'DEL': 'deleteSelected',
        'CTRL+A': 'selectAll',
        'CTRL+C': 'copy',
        'CTRL+X': 'cut',
        'CTRL+V': 'paste',
        'CTRL+ALT+N': 'createFolder'
    // TAB WHILE RENAMING = SWITCH TO RENAME NEXT IN LIST
    // SHIFT + LEFT/RIGHT = EXPAND SELECTION
    };
    
    if (typeof methods[key] !== 'undefined'){
        e.preventDefault();
        var method = methods[key];
        activeExplorer[method].apply(activeExplorer, [e]);
        return false;
    }
    
    return true;
    
};
var listDropHandler = function(je){
    je.preventDefault();
    je.stopPropagation();
    var e = je.originalEvent;
    console.log('list drop', je, e);
    if (e.dataTransfer){
        var dt = e.dataTransfer;
        console.log('dataTransfer', dt, dt.files, dt.files.length);
        if (dt.files && dt.files.length){
            var files = dt.files;
            for (var i = 0; i < files.length; i++) {
                var file = files[i];
                console.log('dropped ' + file.name);
                activeExplorer.upload5(file);
            }
        }
    }
    return false;
};
var allowClick = false;
var listItemAClickHandler = function(e){
    console.log('list item a click', e);
    if (!allowClick){
        e.preventDefault(); // prevents default only
    }else{
        allowClick = false;
    }
//e.stopPropagation(); // leaves only default action
};
var clicks = 0;
var lastClickItem = null;
var listItemAMousedownHandler = function(e){
    clearTimeout(doubleClickTimeout);
    var listItem = e.currentTarget.parentNode;
    console.log('list item a mousedown', clicks, listItem.id);
    if (clicks === 1 && lastClickItem !== null && lastClickItem.id === listItem.id){
        // doubleclick
        e.stopPropagation(); // leaves only default action
        e.currentTarget = listItem;
        listItemDoubleClickHandler(e);
    }else{
        e.preventDefault(); // prevents default only
    }
    clicks = 1;
    lastClickItem = listItem;
    var doubleClickTimeout = setTimeout(function(){
        clicks = 0;
    }, 400);
//e.stopPropagation(); // leaves only default action
};
var listItemDoubleClickHandler = function(e){
    console.log('list item a dblclick', e);
    var li = e.currentTarget;
    if ($(li).hasClass('folder')){
        var explorer = activeExplorer;
        var id = li.id;
        explorer.apiCall({
            action: 'list', 
            id: id
        }, function(response){
            console.log('response', response);
            if (response.status !== 0){
                console.error(response);
            }else{
                explorer.loadList(id, response.result);
                console.log(response);
            }
        });
    }else{
        if (e.type == 'internal'){
            //allowClick = true;
            //var a = $(li).find('a').get(0);
            //a.dispatchEvent(e);
        }else{
            allowClick = true;
        }
    }
}
var listItemMousedownHandler = function(e){
    console.log('list item mousedown', e);
    // Allow passing to listMousedownHandler to activate list
    var li = e.currentTarget;
    var explorer = li.parentNode.yukiFolderExplorer;
    if (e.shiftKey){
        explorer.selectRange(e.currentTarget);
    }else if(e.ctrlKey){
        explorer.select(e.currentTarget, true);
    }else{
        explorer.select(e.currentTarget);
    }
};
var listMousedownHandler = function(e){
    console.log('list mousedown', e);
    e.preventDefault();
    if (activeExplorer !== null){
        activeExplorer.finishRename();
    }
    e.currentTarget.yukiFolderExplorer.activate();
    return false;
};

var explorerInstances = [];
var activeExplorer = null;
var renaming = false;
var deactivateExplorers = function(){
    for (i = 0; i < explorerInstances.length; i++){
        explorerInstances[i].deactivate();
    }
    activeExplorer = null;
};
var folderExplorer = function(list, options){
    $.extend(this, options);
    this.list = list;
    this.init();
};
var folderExplorerPrototype = {
    api: null,
    list: null, // HTMLUListElement
    active: false,
    copy: function(){
    console.log('TODO copy', this.list.id);
},createFolder: function(){
    console.log('createFolder', this.list.id);
    var self = this;
    self.apiCall({
        action: 'create', 
        type: 'folder'
    }, function(response){
        if (response.status !== 0){
            console.error(response);
        }else{
            var newid = response.result.id;
            var explorer = self;
            explorer.apiCall({
                action: 'list', 
                id: self.list.id
            }, function(response){
                console.log('response', response);
                if (response.status !== 0){
                    console.error(response);
                }else{
                    explorer.loadList(response.id, response.result);
                    console.log('select', newid);
                    $(explorer.list).find('a').removeClass('selected');
                    $(explorer.list).find('li[id="' + newid + '"] a').addClass('selected');
                    explorer.startRename();
                }
            });
            console.log(response);
        }
    });
},cut: function(){
    console.log('TODO cut', this.list.id);
},deleteSelected: function(){
    console.log('deleteSelected', this.list.id);
    var self = this;
    $(this.list).find('li:has(a.selected)').each(function(){
        var li = $(this);
        var id = this.id;
        var type = 'file';
        if (li.hasClass('folder')){
            type = 'folder';
        }
        self.apiCall({
            action: 'delete', 
            type: type, 
            id: id
        }, function(response){
            if (response.status !== 0){
                console.error(response);
            }else{
                li.remove(); // remove from list
                console.log(response);
            }
        });
    // TODO update list
    });
},deselect: function(){
    $(this.list).find('a').removeClass('selected');
},finishRename: function(e){
    if (e){ // from event
        console.log('finish rename ', e);
        var target = e.target;
        if ($(target).parents('li').first().attr('id') == renaming){
            return false;
        }
    }
    if (renaming){
        renaming = false;
        var self = this;
        $(this.list).find("li:has(textarea)").each(function(){
            var li = $(this);
            var ta = li.find('textarea');
            var name = ta.val();
            var caption = li.find('.caption');
            caption.text(name);
            var id = this.id;
            var type = 'file';
            if (li.hasClass('folder')){
                type = 'folder';
            }
            self.apiCall({
                action:'rename',
                type: type,
                id: id,
                name: name
            }, function(response){
                if (response.status !== 0){
                    console.error(response);
                }else{
                    console.log(response);
                }
            });
        });
    }
},loadList: function(id, list){
    console.log('load list', this.list.id);
    this.list.id = id;
    var ul = $(this.list);
    ul.html('');
    ul.attr('id', id);
    for (i=0;i<list.length;i++){
        item = list[i];
        var li = $('<li></li>');
        var a = $('<a></a>');
        var icon = $('<span class="icon"></span>');
        var caption = $('<span class="caption"></span>');
        var isFolder = (item.type === 'folder');
        li.attr('id', item.id);
        if (isFolder){
            li.addClass('folder');
        }else{
            a.attr('target', '_blank');
        }
        caption.text(item.name);
        if (item.href){
            a.attr('href', item.href);
        }else{
            a.attr('href', 'javascript:void()');
        }
        if (item.icon){
            var img = $('<img />');
            img.attr('src', item.icon);
            img.appendTo(icon);
        }else{
        }
        icon.appendTo(a);
        caption.appendTo(a);
        a.appendTo(li);
        li.appendTo(ul);
    }
    //$(this.list).html(ul.html());
    this.bind(true);
},navigateDown: function(){
    console.log('TODO navigateDown', this.list.id);
},navigateEnd: function(){
    console.log('navigateEnd', this.list.id);
    this.deselect();
    $(this.list).find('li a').last().addClass('selected');
},navigateHome: function(){
    console.log('navigateHome', this.list.id);
    this.deselect();
    $(this.list).find('li a').first().addClass('selected');
},navigateLeft: function(e){
    var a = $(this.list).find('li:has(a.selected)').first().prev().find('a');
    this.deselect();
    if (a.length){
        a.addClass('selected');
    }else{
        $(this.list).find('a').last().addClass('selected');
    }
},navigateParent: function(e){
    // keyboard: BACKSPACE
    console.log('navigateParent', this.list.id);
    activeExplorer.refresh(true);
},navigateRight: function(){
    console.log('navigateRight', this.list.id);
    var a = $(this.list).find('li:has(a.selected)').last().next().find('a');
    this.deselect();
    if (a.length){
        a.addClass('selected');
    }else{
        $(this.list).find('a').first().addClass('selected');
    }
},navigateSelected: function(e){
    // keyboard: ENTER
    console.log('navigateSelected', this.list.id);
    var a = $(activeExplorer.list).find('a.selected').first().get(0);
    if (a){
        activeExplorer.triggerMouse('dblclick', a);
    //listItemDoubleClickHandler(evt);
    }
},navigateUp: function(){
    console.log('TODO navigateUp', this.list.id);
},paste: function(){
    console.log('TODO paste', this.list.id);
},select: function(li, add){
    if (!add){
        $(this.list).find('a').removeClass('selected');
    }
    if (add && $(this.list).find('a').length > 1){
        $(li).find('a').toggleClass('selected');
    }else{
        $(li).find('a').addClass('selected');
    }
},selectAll: function(){
    console.log('selectAll', this.list.id);
    $(this.list).find('a').addClass('selected');
},selectRange: function(endLi){
    var $startLi = $(this.list).find('li:has(a.selected)').last();
    if (!$startLi.length){
        // simply select one item
        this.select(endLi);
        return;
    }
    //var startLi = $startLi.get(0);
    $(this.list).find('a').removeClass('selected');
    var id = endLi.id;
    if ($startLi.nextAll().filter('li[id="' + id + '"]').length){
        // endLi is after startLi
        $startLi.nextUntil('li[id="' + id + '"]').andSelf().add(endLi).find('a').addClass('selected');
    }else{
        $startLi.prevUntil('li[id="' + id + '"]').andSelf().add(endLi).find('a').addClass('selected');
    }
//$(li).find('a').addClass('selected');
},startRename: function(){
    console.log('startRename', this.list.id);
    var li = $(this.list).find('li:has(a.selected)').first();
    if (li.length){
        li.addClass('renaming');
        var caption = li.find('.caption');
        renaming = true;
        var name = $('<textarea></textarea>');
        name.text(caption.text());
        caption.addClass('caption-rename').html(name);
        var ta = name.get(0);
        ta.style.height = '1px';
        var grow = function(ta){
            ta.style.height = 'auto';
            ta.style.height = ta.scrollHeight+'px';
        };
        grow(ta);
        name.bind('keyup', function(){
            grow(ta);
        }).focus().bind('blur', this.finishRename).bind('mousedown', function(e){
            e.stopPropagation(); // leaves only default action
        });
    };
},triggerMouse: function(type, el, nat){
    if (!nat){
        if (type === 'click'){
            this.triggerMouse('mousedown', el, true);
            this.triggerMouse('mouseup', el, true);
            this.triggerMouse('click', el, true);
            return;
        }else if (type === 'dblclick'){
            this.triggerMouse('click', el);
            this.triggerMouse('click', el);
            return;
        }
    }
    var evt = document.createEvent("MouseEvents");
    evt.initMouseEvent(type, true, true, window,
        0, 0, 0, 0, 0, 
        false, false, false, false, 
        0, null);
    el.dispatchEvent(evt);
    
},
    onDomChange: function(){
        console.log('TODO onDomChange', this.list.id);
    },
    apiCall: function(request, callback){
        if (this.api !== null){
            $.post(this.api, request, callback, 'json');
        }
    },
    activate: function(){
        deactivateExplorers(); // deactivate all explorers
        this.active = true;
        activeExplorer = this;
        $(this.list).removeClass('files-list-inactive').addClass('files-list-active');
    },
    refresh: function(parent){
        var id = this.list.id;
        this.getList(id, parent);
    },
    getList: function(id, parent){
        var explorer = this;
        var args = {
            action: 'list', 
            id: id
        };
        if (parent){
            args['type'] = 'parent';
        };
        explorer.apiCall(args, function(response){
            console.log('response', response);
            if (response.status !== 0){
                console.error(response);
            }else{
                explorer.loadList(response.id, response.result);
                if (parent){
                    console.log('select', id);
                    $(explorer.list).find('li[id="' + id + '"] a').addClass('selected');
                }
                console.log(response);
            }
        });
    },
    upload5: function(file, targetId){
        if (!targetId){
            targetId = this.list.id;
        }
        // TODO check file drop support
        var self = this;
        var xhr = new XMLHttpRequest();
        xhr.open("POST", this.api);
        xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
        xhr.setRequestHeader("X-File-Name", file.fileName);
        xhr.setRequestHeader("X-File-Size", file.fileSize);
        xhr.setRequestHeader("X-Target-Id", targetId);
        xhr.setRequestHeader("Content-Type", "multipart/form-data");
        xhr.onload = function() { 
            /* If we got an error display it. */
            if (xhr.responseText && xhr.responseText !== '{"status":0}') {
                console.error(xhr.responseText);
            }else{
                self.refresh();
            }
        };
        // event.dataTransfer.mozGetDataAt("application/x-moz-file", 0)
        // Starting with Firefox 3.5 Gecko 1.9.2, you may also specify an DOM File
        xhr.send(file); 
    },
    deactivate: function(){
        this.active = false
        $(this.list).addClass('files-list-inactive').removeClass('files-list-active');
    },
    bind: function(skipSelf){
        if (!skipSelf){
            $(this.list).bind('mousedown', listMousedownHandler);
            $(this.list).bind('drop', listDropHandler);
            this.list.ondragenter = this.list.ondragover = function (e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'copy';
                return false;
            };
        }
        $(this.list).find('li').bind('mousedown', listItemMousedownHandler);
        $(this.list).find('a')
        .bind('mousedown', listItemAMousedownHandler)
        .bind('click', listItemAClickHandler);
    },
    call: function(method){
        arguments.shift();
        method.apply(this, arguments);
    },
    apply: function(method, args){
        method.apply(this, args);
    },
    init: function(){
        if (this.api === null){
            return false;
        }
        if (this.list === null){
            return false;
        }
        explorerInstances.push(this);
        $(this.list).addClass('files-list');
        this.bind();
        this.activate(); // activate last initialized explorer
        return true;
    }
};
folderExplorer.prototype = folderExplorerPrototype;
folderExplorer.prototype.constructor = folderExplorer;

var defaults = {
    api: null,
    list: null
};
var keyString = function(e){
    var s = '';
    var k = e.keyCode;
    if (e.ctrlKey){
        s+='CTRL+';
    }
    if (e.altKey){
        s+='ALT+';
    }
    if (e.shiftKey){
        s+='SHIFT+';
    }
    var special = {
        8: 'BACKSPACE',
        13: 'ENTER',
        35: 'END',
        36: 'HOME',
        37: 'LEFT',
        38: 'UP',
        39: 'RIGHT',
        40: 'DOWN',
        46: 'DEL'
    };
    if (typeof special[k] != 'undefined'){
        s += special[k];
    }else if (k >= 112 && k <= 123){ // F1-F12: 112-123
        s += 'F' + (k - 111);
    }else{
        s += String.fromCharCode(k);
    }
    return s; 
};
var globalInitialized = false;
var globalInit = function(options){
    if (globalInitialized){
        return;
    }
    globalInitialized = true;
    $(document)
    .bind('keydown', keydownHandler)
    .bind('click', globalClickHandler)
    .bind('mousedown', globalMousedownHandler);
};



    
$.fn.explorer = function(options){
    globalInit(options);
    return this.each(function() {        
        if (options) { 
            $.extend(defaults, options);
        }
        this.yukiFolderExplorer = new folderExplorer(this, options);
    });
};
}(jQuery));
