
var j = jQuery;

j(document).ready(function(){
    
    if(j.browser.msie && (parseInt(j.browser.version) <= 7)){
        j('.mini_cart_holder').css('width', j('#cart_info').outerWidth());
    }
    
    if(j.browser.msie && (parseInt(j.browser.version) <= 6)){
        alert("Sorry, we detect that you are using \"Microsoft Internet Explorer\" version " + j.browser.version + ", we only support Microsoft Internet Explorer version 7, 8, 9 or greater. Please upgrade your browser.");
        j('.mini_cart_holder').css('width', j('#cart_info').outerWidth());
    }
    
    var timer = 0;
   
    j('#cart_info').hover(function(){
    
        j('#topCartContent').show();
        j("#scroll_container").mCustomScrollbar("update");
        
        if(j('.mCSB_scrollTools').css('display') != 'none')
        {
            j('.mCSB_container .product-details').css('width', '165px');
            j('.mCSB_container').css('margin-right', '20px');
            j('.mini-cart-info > div:first-child').css('width', '50px');
            j('.mini-cart-info > div:last-child').css('width', '110px');
              
        }
        else
        {
            j('.mCSB_container .product-details').css('width', '185px');
            j('.mCSB_container').css('margin-right', '0px');
            j('.mini-cart-info > div:first-child').css('width', '80px');
            j('.mini-cart-info > div:last-child').css('width', '100px');
        }
        
        if(j.browser.msie && (parseInt(j.browser.version) == 7)){
            j('.mCSB_scrollTools .mCSB_draggerRail').css('height', j('.mCSB_scrollTools .mCSB_draggerContainer').css('height'));
            j('.mCSB_scrollTools .mCSB_buttonDown').css('top', j('.mCSB_scrollTools .mCSB_draggerContainer').css('height'));          
        }
    
    }, function(){
        
        var obj = this;
        
        timer = window.setTimeout(function()
        {
           j('#topCartContent').hide();
           
        }, 20);
    
    });
    
    j('#topCartContent').hover(function(){
    
         window.clearTimeout(timer);
    
    }, function(){
        
        j(this).hide();
           
    });
    
    j('.close-btn').click(function(){
        j('#topCartContent').hide();
    });
    
    
    j("#scroll_container").mCustomScrollbar({
        scrollButtons:{
            enable: true
        },
        mouseWheelPixels: 10000,
        scrollInertia: 100,
        mouseWheel: true,
        theme: 'dark-thick'
    });
    
    //j('#topCartContent').hide();
    
    /*var headID = document.getElementsByTagName("body")[0];       
    var newScript = document.createElement('script');
    newScript.type = 'text/javascript';
    newScript.src = '/js/fetchauto/jquery.mCustomScrollbar.concat.min.js';
    //j('body').append('<script type="text/javascript" src="/js/fetchauto/jquery.mCustomScrollbar.concat.min.js"></script>');
    //j(newScript).appendTo('body');
    headID.appendChild(newScript);
    */
    j("#scrollable").scrollable({
        circular: true
        //speed: 5000     
    });
    
    
    /*
     * 
     *  The following code fix the height of view mode is different in different browser.
     *
     *  1. when page loaded
     *  2. when new_product DOM modified
     *  
     */
    
    var fix_view_mode_height = function(){
        if((j(".sorter .view-mode").size() > 0) && (j(".sorter .limiter").size() > 0) && (j(".sorter .sort-by").size() > 0)) {
            
            j(".sorter .view-mode").css('line-height', j(".sorter .limiter").css('height'));
            j(".sorter .pages").css('line-height', j(".sorter .limiter").css('height'));
        }
        
    };
    
    
    fix_view_mode_height(); 
    
    j(".new_product").bind("DOMSubtreeModified", fix_view_mode_height);

    /********************************************************************************************************/
    
    /*
    if((j('.cms-index-index .std').size() > 0) && (j('.cms-index-index .std .cms-boxes p').size() > 1)){
        
        j('.cms-index-index .std .cms-boxes p').each(function(index, element){
            if(index > 0)  j(element).hide();
        });
        
        j(j('.cms-index-index .std .cms-boxes p').get(0)).after('<div class="read_more">Read more&raquo;</div>');
    }
    */
   
    /*if((j('.catalog-product-view .product-view .short-description .std').size() > 0) && (j('.catalog-product-view .product-view .short-description .std ul li').size() > 4)){
        
        j('.catalog-product-view .product-view .short-description .std ul li').each(function(index, element){
            if(index > 4)  j(element).hide();
        });
        
        j(j('.catalog-product-view .product-view .short-description .std ul li').get(4)).after('<li style="color:#000;cursor:pointer;text-decoration:underline;list-style-type:none;padding-top:10px;" class="read_more">Learn More</li>');
    }*/
    
    /*
    if((j('.category-description .category-description-content').size() > 0) && (j('.category-description .category-description-content p').size() > 1)){
        
        j('.category-description .category-description-content p').each(function(index, element){
            if(index > 0)  j(element).hide();
        });
        
        j(j('.category-description .category-description-content p').get(0)).after('<p class="read_more">Read more&raquo;</p>');
    }
    */
    j('.read_more').click(function(){
    
        j(this).nextAll().show();
        j(this).remove();       
    });
    
    j( "#tabs" ).tabs();
    
    /*
    if(j('#super-product-table td span').html() == 'No options of this product are available.')
    {
        
    }
    */
   
    if(j('.cart').size() > 0)
    {
        j('select[name^="cart["]').change(function() {
            j(this).parent().find('button').show();
        });
    }
   
    j('#cart_info').click(function(){
        
        var regex = /(<([^>]+)>)/ig;
        var result = parseInt(j.trim(j('#cart_info').html().replace(regex, "")));
        if(result != '0') location.href = '/checkout/cart';
    });
    
    if(j('.my-wishlist').size() > 0)
    {
        j('select[name^="qty["]').change(function() {
            j(this).parent().find('button').show();
        });
    }
    
    /*
    if(j('ul.messages .success-msg').size() > 0)
    {
        j('.cart-added').fadeIn();
    }
    
    if(j('ul.messages .error-msg').size() > 0)
    {
        alert(j('ul.messages .error-msg span').html());
    }
    */
    
    j('.ratings .rating-links a[href="#fragment-2"], .first_review .review a[href="#fragment-2"]').click(function(){
        j('#tabs > ul > li > a[href="#fragment-2"]').trigger("click");
    });
    
    if(j('#tabs > ul > li > a[href="#fragment-2"]').size() > 0 && (location.hash == '#fragment-2'))
    {
        j('#tabs > ul > li > a[href="#fragment-2"]').trigger("click");
    }
    

    if(j('.catalog-product-view #tabs').size() > 0)
    {
    	j('a[href="#fragment-2"]').click(function(){
	 		
	 		if(j.trim(j('#vehicle_fitment_list').html()) == '')
	 		{   	
		    	var sku = j('#sku_vehicle_fitment').val();
		
		    	j.ajax({
		    		url: "/shell/api/vehicle-fitment.php", 
		    		data: {sku: sku},
		    		success: function(html)
		    		{
				 		j('#vehicle_fitment_list').append(html);
				 		j('#loading_icon').remove();
				 		j('#vehicle_fitment_display').show();
					},
					error: function(){}, 
					dataType: 'html'
				});
			}
			
		});
    }
	
});
