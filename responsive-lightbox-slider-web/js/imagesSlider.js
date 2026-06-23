$.fn.mySliderPlugin=function(){
    return this.each(function(){
    var slider = $(this);
    if (slider.data("images-slider-ready")) {
        return;
    }
    slider.data("images-slider-ready", true);
    var fullScreenSection='<section class="fullScreen"><button class="sliderLightboxButton" id="prevIcon" type="button" aria-label="Previous image"><i class="fas fa-solid fa-chevron-left"></i></button><img src="" id="maximizedImg" alt=""><button class="sliderLightboxButton" id="nextIcon" type="button" aria-label="Next image"><i class="fas fa-solid fa-chevron-right"></i></button><button class="sliderLightboxButton" id="cancelIcon" type="button" aria-label="Close"><i class="fa fa-times" aria-hidden="true"></i></button></section>';
    
    slider.parent().append(fullScreenSection);
    slider.addClass("imagesSec");
    var fullScreen = slider.parent().find(".fullScreen").last();
    var maximizedImg = fullScreen.find("#maximizedImg");
    var nextIcon = fullScreen.find("#nextIcon");
    var prevIcon = fullScreen.find("#prevIcon");
    var cancelIcon = fullScreen.find("#cancelIcon");
    var hoverScrollTimer = null;

    /////////////// events on this slider plugin ///////////////

    function itemScrollAmount() {
        var firstImg = slider.find("img").first();
        if (!firstImg.length) {
            return 220;
        }
        return firstImg.outerWidth(true);
    }

    function stopHoverScroll() {
        if (hoverScrollTimer) {
            clearInterval(hoverScrollTimer);
            hoverScrollTimer = null;
        }
    }

    function startHoverScroll(direction) {
        if (hoverScrollTimer && slider.data("hover-scroll-direction") === direction) {
            return;
        }
        stopHoverScroll();
        slider.data("hover-scroll-direction", direction);
        function scrollOnce() {
            slider[0].scrollBy({
                left: direction * itemScrollAmount(),
                behavior: "smooth"
            });
        }
        scrollOnce();
        hoverScrollTimer = setInterval(scrollOnce, 520);
    }

    slider.find("img").on("click",function(){
    maximizedImg.attr("src",$(this).attr("src"));
    fullScreen.fadeIn(200).css("display","flex");
    });

    slider.on("mousemove",function(e){
        if (slider[0].scrollWidth <= slider[0].clientWidth) {
            stopHoverScroll();
            return;
        }

        var rect = slider[0].getBoundingClientRect();
        var x = e.clientX - rect.left;
        var edgeSize = Math.min(90, rect.width * 0.22);

        if (x <= edgeSize) {
            startHoverScroll(-1);
        } else if (x >= rect.width - edgeSize) {
            startHoverScroll(1);
        } else {
            stopHoverScroll();
        }
    });

    slider.on("mouseleave",function(){
        stopHoverScroll();
    });
    
    
    nextIcon.on("click",function(e){
    // e.stopPropagation();  //and remove the if statement in fullScreen event 
    e.stopPropagation();
    var current = slider.find("img").filter(function(){ return $(this).attr("src") === maximizedImg.attr("src"); });
    var nextImg = current.next("img").attr("src");

    if(nextImg ==undefined){
        nextImg=slider.find("img").first().attr("src");
    }
    maximizedImg.fadeTo(200,0.50, function() {
        maximizedImg.attr("src",nextImg);
        maximizedImg.fadeTo(200,1);
    });
    })
    
    prevIcon.on("click",function(e){
    e.stopPropagation();
    var current = slider.find("img").filter(function(){ return $(this).attr("src") === maximizedImg.attr("src"); });
    var prevImg = current.prev("img").attr("src");

    if(prevImg ==undefined){
        prevImg=slider.find("img").last().attr("src");
    }
    maximizedImg.fadeTo(200,0.50, function() {
        maximizedImg.attr("src",prevImg);
        maximizedImg.fadeTo(200,1);
    });
    })


    fullScreen.on("click",function(e){
    if($(e.target)[0] != nextIcon[0]  &&  $(e.target)[0] != prevIcon[0]){
        fullScreen.fadeOut(200)
    }
    });

    cancelIcon.on("click",function(e){
        e.stopPropagation();
        fullScreen.fadeOut(200);
    });

    slider.parent().find("#selectRandom").on("click",function(){
        var listOfImgs = slider.find("img");
        var randomImg = listOfImgs[Math.floor(Math.random()*(listOfImgs.length))];
        
        maximizedImg.attr("src",$(randomImg).attr("src"));
        fullScreen.fadeIn(200).css("display","flex");
    });
    });
}

$(function(){
    $(".useSliderPlugin").mySliderPlugin();
});
