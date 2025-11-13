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
03. Mobile Menu Toggle
********************/
    var registeredMobileMenus = [];
    var setupMobileMenuToggle = function () {
        var menuAreas = document.querySelectorAll('.mobile-menu-area .mobile-menu');
        if (!menuAreas.length) {
            return;
        }

        menuAreas.forEach(function (menu) {
            var nav = menu.querySelector('nav');
            if (!nav) {
                return;
            }

            if (menu.querySelector('.mobile-menu__toggle')) {
                return;
            }

            var toggle = document.createElement('button');
            toggle.type = 'button';
            toggle.className = 'mobile-menu__toggle';
            toggle.setAttribute('aria-expanded', 'false');
            toggle.setAttribute('aria-label', 'Mobil menüyü aç/kapat');
            toggle.innerHTML = '<span class="mobile-menu__toggle-bar"></span><span class="mobile-menu__toggle-bar"></span><span class="mobile-menu__toggle-bar"></span>';
            menu.insertBefore(toggle, nav);

            var navHeader = document.createElement('div');
            navHeader.className = 'mobile-menu__header';
            var logoLink = menu.parentElement.querySelector('.logo a');
            if (logoLink) {
                var brandClone = logoLink.cloneNode(true);
                brandClone.classList.add('mobile-menu__brand');
                navHeader.appendChild(brandClone);
            } else {
                var brandTitle = document.createElement('span');
                brandTitle.textContent = document.title || 'Menü';
                brandTitle.className = 'mobile-menu__brand-text';
                navHeader.appendChild(brandTitle);
            }
            var closeBtn = document.createElement('button');
            closeBtn.type = 'button';
            closeBtn.className = 'mobile-menu__close';
            closeBtn.setAttribute('aria-label', 'Menüyü kapat');
            closeBtn.textContent = 'X';
            navHeader.appendChild(closeBtn);
            nav.insertBefore(navHeader, nav.firstChild);

            var navList = nav.querySelector('ul');
            if (navList) {
                navList.classList.add('mobile-menu__list');
            }

            var closeMenu = function () {
                menu.classList.remove('is-open');
                toggle.setAttribute('aria-expanded', 'false');
                document.body.classList.remove('is-mobile-menu-open');
            };

            toggle.addEventListener('click', function () {
                var isOpen = menu.classList.toggle('is-open');
                toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                document.body.classList.toggle('is-mobile-menu-open', isOpen);
            });

            closeBtn.addEventListener('click', closeMenu);

            nav.querySelectorAll('a').forEach(function (link) {
                link.addEventListener('click', closeMenu);
            });

            registeredMobileMenus.push({
                menu: menu,
                toggle: toggle,
                close: closeMenu
            });
        });
    };

    setupMobileMenuToggle();

    window.addEventListener('resize', function () {
        if (window.innerWidth > 767) {
            registeredMobileMenus.forEach(function (entry) {
                entry.close();
            });
        }
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
        margin:20,
        nav:false,
        dots:true,
        autoplay: true,
        autoplayHoverPause: true,
        responsive:{
            0:{ items:1 },
            768:{ items:1 },
            1200:{ items:1 }
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
Document upload keyboard support
********************/
    var documentUploadLabels = document.querySelectorAll('.document-upload__label[for]');
    if (documentUploadLabels.length) {
        documentUploadLabels.forEach(function (label) {
            label.addEventListener('keydown', function (event) {
                if (event.key !== 'Enter' && event.key !== ' ') {
                    return;
                }
                event.preventDefault();
                var targetId = label.getAttribute('for');
                if (!targetId) {
                    return;
                }
                var targetInput = document.getElementById(targetId);
                if (targetInput && typeof targetInput.click === 'function') {
                    targetInput.click();
                }
            });
        });
    }

/********************
17. Preloader
********************/
    $(window).on('load', function() {
        $('#preloader').delay(350).fadeOut('slow');
        $('body').delay(350).css({'overflow':'visible'});
    });

})(jQuery);
