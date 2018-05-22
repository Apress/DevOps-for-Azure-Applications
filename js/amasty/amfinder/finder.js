/**
 * @author Amasty Team
 * @copyright Copyright (c) 2010-2011 Amasty (http://www.amasty.com)
 * @package Amasty_Finder
 */
var amFinder = new Class.create();


// CHEN SEI-LIM 2014-02-25

amFinder.prototype = {
    initialize: function(containerId, ajaxUrl, loadingText, isNeedLast)
    {
        this.containerId = containerId;
        this.ajaxUrl	 = ajaxUrl;
        this.loadingText = loadingText;
        this.isNeedLast  = Number(isNeedLast);
        this.selects     = new Array();

        //possible bug if select order in the HTML will be different
        $$('#' + this.containerId + ' select').each(function(select){
            select.observe('change', this.onChange.bindAsEventListener(this));
            this.selects[this.selects.length] = select;
        }.bind(this));
    },

    onChange: function(event)
    {
        var select     = Event.element(event);
        var parentId   = '';	//select.value;
        var dropdownId = 0;
        var hold = [];

        /* should load next element's options only if current is not the last one */
        for (var i = 0; i < this.selects.length; i++){
            var value = this.selects[i].value;

            //console.log (i+':'+value);

            if (value != '999999') {
                hold.push (this.selects[i].value);
            }

            if (this.selects[i].id == select.id && i != this.selects.length-1){
                var selectToReload = this.selects[i + 1];
                if (selectToReload){
                    dropdownId = selectToReload.id.substr(selectToReload.id.search('--') + 2);
                }
                break;
            }
        }

        parentId = hold.join ('.');
        this.clearAllBelow(select);

        if (0 != parentId && dropdownId){
            var postData = 'dropdown_id=' + dropdownId + '&parent_id=' + parentId;
            new Ajax.Request(this.ajaxUrl, {
                method: 'post',
                postBody : postData,
                evalJSON : 'force',

                onLoading: function(){
                    this.showLoading(selectToReload);
                }.bind(this),

                onSuccess: function(transport) {
                    if (transport.responseJSON){
                        this.clearSelectOptions(selectToReload);
                        var itemsFound = false;
                        transport.responseJSON.each(function(item){
                            itemsFound = true;
                            var option = document.createElement('option');
                            option.value = item.value;
                            option.text  = item.label;
                            option.label = item.label;
                            $(selectToReload).appendChild(option);
                        });

                        if (itemsFound){
                            $(selectToReload).disabled = false;
                        }
                    }
                }.bind(this)
            });
        }
    },

    isLast: function(select)
    {
        return (this.selects[this.selects.length-1].id == select.id);
    },

    isFirst: function(select)
    {
        return (this.selects[0].id == select.id);
    },

    clearSelectOptions: function(select)
    {
        $(select).descendants().each(function(option){
            if(option.value!=999999){
                option.remove();
            }
        });
    },

    clearAllBelow: function(select)
    {
        var startClearing = false;
        for (var i = 0; i < this.selects.length; i++){
            if (startClearing){
                this.clearSelectOptions(this.selects[i]);
                $(this.selects[i]).disabled = true;
            }
            if (this.selects[i].id == select.id){
                startClearing = true;
            }
        }
        var type = (((this.isLast(select) && !this.isNeedLast) && select.value > 0) || ((this.isNeedLast) && ((select.value > 0) || (!this.isFirst(select))))) ? 'block' : 'none';
        $$('#' + this.containerId + ' .amfinder-buttons')[0].style.display = type;
    },

    showLoading: function(selectToReload)
    {
        var option = document.createElement('option');
        option.value = 0;
        option.text  = this.loadingText;
        option.label = this.loadingText;
        $(selectToReload).appendChild(option);
    }
};