var amshopby_working  = false;
var amshopby_blocks   = {};

function amshopby_ajax_init(){
    $$('div.block-layered-nav a', amshopby_toolbar_selector + ' a').
        each(function(e){
            var p = e.up();
            if (p.hasClassName('amshopby-cat') || p.hasClassName('amshopby-clearer')){
               return;
            }

            e.onclick = function(){
                if (this.hasClassName('checked')) {
                    this.removeClassName('checked');
                } else {
                    this.addClassName('checked');
                }
                
                var s = this.href;
                if (s.indexOf('#') > 0){
                    s = s.substring(0, s.indexOf('#'))
                }
                amshopby_ajax_request(s);
                return false;
            };
        });
    
    $$('div.block-layered-nav select.amshopby-ajax-select', amshopby_toolbar_selector + ' select').
        each(function(e){
            e.onchange = 'return false';
            Event.observe(e, 'change', function(e){
                amshopby_ajax_request(this.value);
                Event.stop(e);
            });
        });
    if (typeof(amshopby_external) != 'undefined'){
        amshopby_external();    
    }
}

function amshopby_get_created_container()
{
    var elements = document.getElementsByClassName('amshopby-page-container');
    return (elements.length > 0) ? elements[0] : null;
}

function amshopby_get_container()
{
    var createdElement = amshopby_get_created_container();
    if (!createdElement) {
        var container_element = null;
        
        var elements = $$('div.category-products');
        if (elements.length == 0) {
            container_element = amshopby_get_empty_container();
        } else {
            container_element = elements[0];
        }
        
        if (!container_element) {
            alert('Please add the <div class="amshopby-page-container"> to the list template as per installation guide. Enable template hints to find the right file if needed.');
        }    
        
        container_element.wrap('div', { 'class': 'amshopby-page-container', 'id' : 'amshopby-page-container' });
        
        createdElement = amshopby_get_created_container();
        
        $(createdElement).insert({ bottom : '<div style="display:none" class="amshopby-overlay"><div></div></div>'});
    }
    return createdElement;
}

function amshopby_get_empty_container()
{
    var notes = document.getElementsByClassName('note-msg');
    if (notes.length == 1) {
        return notes[0];
    }
}

/*
 * Get location object from string 
 */
var amshopby_get_location_from_string = function(href) {
    var l = document.createElement("a");
    l.href = href;
    return l;
};



function amshopby_ajax_request(url){
    
    if (amshopby_use_hash) {
        
        amshopby_skip_hash_change = true;
        var url = url.replace(window.top.location.protocol + '//' + window.top.location.host, '');
        window.top.location.hash = encodeURIComponent(url); 
                        
        /*
         * Clean hash param to avoid scrolling page down
         */
        if (typeof amscroll_object != 'undefined') {
            amscroll_object.setHashParam('page', null);
            amscroll_object.setHashParam('top', null);
        }
            
        if (typeof amscroll_object != 'undefined') {
            var tmpUrl = window.top.location.protocol + '//' + window.top.location.host + url;
            amscroll_params.url = tmpUrl;
            amscroll_object.setUrl(tmpUrl);
        }
    }
    
    var block = amshopby_get_container();
    
    if (block && amshopby_scroll_to_products) {
        block.scrollTo();
    }

    amshopby_working = true;
    
    $$('div.amshopby-overlay').each(function(e){
        e.show();
    });

    var request = new Ajax.Request(url,{
            method: 'get',
            parameters:{'is_ajax':1},
            onSuccess: function(response){
                data = response.responseText;
                if(!data.isJSON()){
                    setLocation(url);
                }
                
                data = data.evalJSON();
                if (!data.page || !data.blocks){
                    setLocation(url);
                }
                amshopby_ajax_update(data);
                amshopby_working = false;
                amshopby_skip_hash_change = false;
            },
            onFailure: function(){
                amshopby_working = false;
                setLocation(url);
            }
        }
    );
}

function amshopby_get_first_descendant(element) {
    
    var targetElement = element.firstChild;
    if(typeof element.firstDescendant != "undefined") {
        targetElement = element.firstDescendant();
    }
    return targetElement;
}

function amshopby_ajax_update(data){

    //update category (we need all category as some filters changes description)
    var tmp = document.createElement('div');
    tmp.innerHTML = data.page;
    
    
    var block = amshopby_get_container();
    if (block) {
        var targetElement = amshopby_get_first_descendant(tmp);
        
        /*
         * If returned element is not HTML tag
         */
        if (targetElement == null) {            
            tmp.innerHTML = '<p class="note-msg">' + data.page + '</p>';
            targetElement = amshopby_get_first_descendant(tmp);
        }
        block.parentNode.replaceChild(targetElement, block);
    }

    var blocks = data.blocks;
    for (id in blocks){
        var html   = blocks[id];
        if (html){
            tmp.innerHTML = html;
        }
        
        block = $$('div.'+id)[0];
        if (html){
            if (!block){
                block = amshopby_blocks[id]; // the block WAS in the structure a few requests ago
                amshopby_blocks[id] = null;    
            }
            if (block){
                var targetElement = amshopby_get_first_descendant(tmp);
                block.parentNode.replaceChild(targetElement, block);
            }
        }
        else { // no filters returned, need to remove
            if (block){
                var empty = document.createTextNode('');
                amshopby_blocks[id] = empty; // remember the block in the DOM structure
                block.parentNode.replaceChild(empty, block);        
            }
        }  
    }
    amshopby_start(); 
    amshopby_ajax_init();
     
}

function amshopby_request_required()
{    
    if (typeof amscroll_object != 'undefined') {
        
        if (amshopby_use_hash && window.top.location.hash) {
            var hash = amscroll_object.getUrlParam();
            for (var item in hash) {
                if (!hash.hasOwnProperty(item)) {
                  continue;
                }
                if (item != 'page' && item != 'top') {
                    return true;
                }
            }
        }
    } else {
        if (amshopby_use_hash && window.top.location.hash) {
            return true;
        }
    }
    return false;    
}


document.observe("dom:loaded", function(event) {
    
    amshopby_ajax_init();
    
    if (amshopby_request_required()) {
        var hash = decodeURIComponent(window.top.location.hash.substr(1));
        var url = window.top.location.protocol + '//' + window.top.location.host;
        
        url = url + hash;
        
        amshopby_ajax_request(url);
    } 
});

var amshopby_toolbar_selector = 'div.toolbar';
var amshopby_scroll_to_products = false;
var amshopby_use_hash = false;
var amshopby_skip_hash_change = false;

var AnchorChecker = {
        initialize: function(){
            this.location = window.top.location.hash;
            this.interval = setInterval(function(){
                
             if (this.location != window.top.location.hash) 
             {
                 if (this.location != '') { 
                     this.anchorAltered();
                 }
                 this.location = window.top.location.hash;
             }
           }.bind(this), 500); // check every half second
         },
         anchorAltered: function(){
             if (!amshopby_skip_hash_change) {
                 amshopby_ajax_request(decodeURIComponent(window.top.location.hash.substr(1)));
             }
         }
    };
if (amshopby_use_hash) {
        AnchorChecker.initialize();
}
        
function amshopby_external(){
    //add here all external scripts for page reloading
    // like igImgPreviewInit(); 
    if (typeof amscroll_object != 'undefined') {
        amscroll_object.init(amscroll_params); 
        amscroll_object.bindClick();
    }
    
    if (typeof amshopby_demo != 'undefined') {
        amshopby_demo();
    }
}