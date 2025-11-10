/* -----------------------------------
Template:  junction - event meeting conference business template
Author: RoyHridoy
Version: 1.0
Design and Developed by: Hridoy Roy

NOTE: This is main js file. All js plugin active & custom js included in this file.

--------------------------------------*/

/*================================================
[  Table of contents  ]
==================================================
01. Top Menu Stick
02. Url Active Class
03. jQuery MeanMenu
04. SmoothSroll
05. Scrollspy
06. countdown
07. venobox
08. wow js
09. speakers
10. clients
11. blog-slider
12. stellar
13. CounterUp
14. ScrollUp
15. Contact form
16. Gradient
17. Preloader

======================================
[ End table content ]
======================================*/

(function ($) {
    "use strict";

/*******************
01. Top Menu Stick
********************/
    var sticky_menu = $('#sticker, #sticker-mobile');
    var pos = sticky_menu.position();
    if (sticky_menu.length) {
        var windowpos = sticky_menu.offset().top;
        $(window).on('scroll', function() {
            var windowpos = $(window).scrollTop();
            if (windowpos > pos.top) {
                sticky_menu.addClass('stick');
            } else {
                sticky_menu.removeClass('stick');
            }
        });
    }

/*******************
02. Url Active Class
********************/
    $(function() {
        var pgurl = window.location.href.substr(window.location.href
                                                .lastIndexOf("/")+1);
        $(".mainmenu a").each(function(){
            if($(this).attr("href") === pgurl || $(this).attr("href") === '' )
                $(this).addClass("active");
        })
    });

    /*********** li active class ***********/
    var cururl = window.location.pathname;
    var curpage = cururl.substr(cururl.lastIndexOf('/') + 1);
    if(curpage === "" || curpage === "/" || curpage === "admin") {
        curpage = "index.html";
    }
    var hash = window.location.hash.substr(1);
    $(".mainmenu li").removeClass("active");
    var $targetLink = $();
    if(hash) {
        $targetLink = $(".mainmenu li a").filter(function () {
            var anchorHash = (this.hash || "").replace("#", "");
            return anchorHash === hash;
        }).first();
    }
    if(!$targetLink.length) {
        $targetLink = $(".mainmenu li a").filter(function () {
            var href = $(this).attr("href") || "";
            if(href === "" || href === "#") {
                return false;
            }
            var parts = href.split("#");
            var pagePart = parts[0];
            pagePart = pagePart.substr(pagePart.lastIndexOf('/') + 1);
            if(pagePart === "" || pagePart === "#") {
                pagePart = "index.html";
            }
            if(pagePart !== curpage) {
                return false;
            }
            if(parts[1]) {
                if(hash === "") {
                    return false;
                }
                return parts[1] === hash;
            }
            return hash === "";
        }).first();
    }
    if(!$targetLink.length) {
        $targetLink = $(".mainmenu li").first().find("a").first();
    }
    $targetLink.parents("li").addClass("active");

/*******************
03. jQuery MeanMenu
********************/
    $('.mobile-menu nav').meanmenu({
        meanScreenWidth: "768",
        meanMenuContainer: ".mobile-menu",
        meanMenuOpen: '<span class="meanmenu__bar"></span><span class="meanmenu__bar"></span><span class="meanmenu__bar"></span>',
        meanMenuClose: '<span class="meanmenu__close"></span>',
        meanMenuCloseSize: "0"
    });

/*******************
03b. Hero Video Audio
********************/
    var heroVideoEl = document.querySelector('.hero-video');
    if (heroVideoEl) {
        var requestGestureUnlock = function () {
            document.addEventListener('click', unlockWithGesture, { once: true });
            document.addEventListener('touchend', unlockWithGesture, { once: true });
        };

        var unlockWithGesture = function () {
            attemptUnmutePlayback();
        };

        var attemptUnmutePlayback = function () {
            heroVideoEl.muted = false;
            heroVideoEl.volume = 1;
            var resumePromise = heroVideoEl.play();
            if (resumePromise && typeof resumePromise.catch === 'function') {
                resumePromise.catch(function () {
                    requestGestureUnlock();
                });
            }
        };

        heroVideoEl.muted = true;
        var playPromise = heroVideoEl.play();

        if (playPromise && typeof playPromise.then === 'function') {
            playPromise.then(function () {
                setTimeout(function () {
                    attemptUnmutePlayback();
                }, 200);
            }).catch(function () {
                requestGestureUnlock();
            });
        } else {
            requestGestureUnlock();
        }
    }

/*******************
04. SmoothSroll
********************/
    $('.smooth, .smooth-scroll a').on('click', function (event) {
        var $anchor =$(this);
        var headerH ='69';
        $('html, body').stop().animate({
            'scrollTop': $($anchor.attr('href')).offset().top - headerH + "px"
        }, 1200, 'easeInOutExpo');
        event.preventDefault();
    });

/*******************
05. Scrollspy
********************/
    $('body').scrollspy({ target: '.navbar-collapse',offset: 95 })

/*******************
06. countdown
********************/
    $('[data-countdown]').each(function() {
        var $this = $(this), finalDate = $(this).data('countdown');
        $this.countdown(finalDate, function(event) {
        $this.html(event.strftime('<span class="cdown days"><span class="time-count">%-D</span> <p>GÜN</p></span> <span class="cdown hour"><span class="time-count">%-H</span> <p>SAAT</p></span> <span class="cdown minutes"><span class="time-count">%M</span> <p>DAKİKA</p></span> <span class="cdown second"><span class="time-count">%S</span> <p>SANİYE</p></span>'));
        });
    });

/*******************
07. venobox
********************/
    $('.venobox').venobox();

/********************
08. wow js
********************/
    new WOW().init();

/********************
09. speakers
********************/
    $('.speakers').owlCarousel({
        loop:true,
        margin:15,
        nav:false,
        dots:false,
        autoplay: true,
        autoplayHoverPause: true,
        responsive:{
            0:{
                items:1
            },
            480:{
                items:2
            },
            767:{
                items:3
            },
            1000:{
                items:4
            }
        }
    });

/********************
10. clients
********************/
    $('.clients').owlCarousel({
        loop:true,
        margin:10,
        nav:false,
        dots:false,
        autoplay: true,
        autoplayHoverPause: true,
        responsive:{
            0:{
                items:1
            },
            480:{
                items:2
            },
            600:{
                items:3
            },
            1000:{
                items:5
            }
        }
    });

/********************
11. blog-slider
********************/
    $('.blog-slider').owlCarousel({
        loop:true,
        margin:10,
        nav:false,
        dots:false,
        autoplay: true,
        autoplayHoverPause: true,
        responsive:{
            0:{
                items:1
            },
            480:{
                items:1
            },
            600:{
                items:2
            },
            1000:{
                items:3
            }
        }
    })

/********************
12. stellar
********************/
    $.stellar({
        horizontalScrolling: false,
        verticalOffset: 40
    });

/********************
13. CounterUp
********************/
    $('.counter').counterUp({
        delay: 20,
        time: 2000
    });

/********************
14. ScrollUp
********************/
    $.scrollUp({
        scrollText: '<i class="fa fa-angle-double-up"></i>',
        easingType: 'linear',
        scrollSpeed: 900,
        animation: 'fade'
    });

/********************
15. Contact form
********************/
    $('.contact-form-btn button, .open-contact').on('click', function(){
        $('.contact-overlay').slideDown(500);
    });
    $('.contact-overlay .close-form').on('click', function(){
        $('.contact-overlay').slideUp(500);
    });

/********************
16. Gradient
********************/
    $('.gradient').gradientify({
        gradients: [
            { start: [49,76,172], stop: [242,159,191] },
            { start: [255,103,69], stop: [240,154,241] },
            { start: [33,229,241], stop: [235,236,117] }
        ]
    });

/********************
17. Preloader
********************/
    $(window).on('load', function() {
        $('#preloader').delay(350).fadeOut('slow');
        $('body').delay(350).css({'overflow':'visible'});
    });

})(jQuery);
