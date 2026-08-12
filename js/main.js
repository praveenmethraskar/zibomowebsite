/* ==========================================================================
   ZIBOMO — Smart Lockers
   js/main.js — all site interactions and animations.

   Stack: jQuery 3.7 for DOM/eventing, native IntersectionObserver for the
   scroll-reveal engine (cheaper than scroll handlers), Bootstrap's own JS for
   the carousel / tabs / modal / collapse behaviour.

   MODULES
     01 .. Preloader
     02 .. Scroll progress bar
     03 .. Sticky navbar
     04 .. Smooth scrolling + mobile menu
     05 .. Scrollspy (active nav link)
     06 .. Scroll-reveal engine
     07 .. Animated counters
     08 .. "How it works" connector line
     09 .. Product tabs sliding pill
     10 .. Hero carousel niceties
     11 .. About image parallax
     12 .. Video modal
     13 .. Back to top
     14 .. Magnetic buttons
     15 .. Contact form validation
   ========================================================================== */

(function ($) {
  'use strict';

  /* Shared helpers ------------------------------------------------------- */
  var $win  = $(window);
  var $doc  = $(document);
  var $html = $('html');

  // Respect the OS "reduce motion" setting throughout.
  var reduceMotion = window.matchMedia &&
                     window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* Throttle with requestAnimationFrame so scroll work happens once a frame. */
  function rafThrottle(fn) {
    var ticking = false;
    return function () {
      var args = arguments, ctx = this;
      if (ticking) { return; }
      ticking = true;
      window.requestAnimationFrame(function () {
        ticking = false;
        fn.apply(ctx, args);
      });
    };
  }


  $(function () {

    /* Everything below runs inside one guard. If any module throws, the
       `.js` class is dropped, which makes css/style.css stop hiding the
       scroll-reveal elements — a broken script can never leave the page
       blank. */
    try {

      /* ==================================================================
         01. PRELOADER
         Hidden on window.load, with a hard timeout so a slow/failed asset
         can never trap the visitor behind the splash.
         ================================================================== */
      (function initPreloader() {
        var $pre   = $('#preloader');
        var hidden = false;

        function dismiss() {
          if (hidden) { return; }
          hidden = true;
          $pre.addClass('is-done');
          $html.css('overflow', '');
          window.setTimeout(function () { $pre.remove(); }, 700);

          /* The overflow lock above suppresses the browser's own fragment
             scroll, so an external deep link (…/index.html#contact) would
             otherwise land at the top of the page. Re-apply it by hand now
             that scrolling is possible again, offset for the fixed navbar. */
          if (window.location.hash.length > 1) {
            var $target = $(window.location.hash);
            if ($target.length) {
              var navH = $('#mainNav').outerHeight() || 80;
              $win.scrollTop(Math.max(0, $target.offset().top - navH + 1));
            }
          }
        }

        $html.css('overflow', 'hidden');
        $win.on('load', function () { window.setTimeout(dismiss, 350); });
        window.setTimeout(dismiss, 4000); // failsafe
      }());


      /* ==================================================================
         02. SCROLL PROGRESS BAR
         ================================================================== */
      (function initProgress() {
        var $bar = $('#scrollProgress');

        var update = rafThrottle(function () {
          var scrollable = $doc.height() - $win.height();
          var pct = scrollable > 0 ? ($win.scrollTop() / scrollable) * 100 : 0;
          $bar.css('width', Math.min(100, Math.max(0, pct)) + '%');
        });

        $win.on('scroll resize', update);
        update();
      }());


      /* ==================================================================
         03. STICKY NAVBAR
         Adds .is-stuck past the fold — style.css swaps the transparent
         hero treatment for the solid, blurred bar.
         ================================================================== */
      (function initStickyNav() {
        var $nav = $('#mainNav');

        var update = rafThrottle(function () {
          $nav.toggleClass('is-stuck', $win.scrollTop() > 60);
        });

        $win.on('scroll', update);
        update();
      }());


      /* ==================================================================
         04. SMOOTH SCROLLING + MOBILE MENU
         Uses jQuery animate (not CSS scroll-behaviour) so the fixed navbar
         height can be subtracted from the destination.
         ================================================================== */
      (function initSmoothScroll() {
        var $collapse = $('#navbarSupportedContent');

        $doc.on('click', 'a[href^="#"]:not([href="#"]):not([data-bs-toggle])', function (e) {
          var $target = $(this.getAttribute('href'));
          if (!$target.length) { return; }

          e.preventDefault();

          var navH = $('#mainNav').outerHeight() || 80;
          var top  = $target.offset().top - navH + 1;

          $('html, body').stop().animate(
            { scrollTop: Math.max(0, top) },
            reduceMotion ? 0 : 850,
            'swing'
          );

          // Collapse the mobile drawer after choosing a destination.
          if ($collapse.hasClass('show')) {
            var inst = bootstrap.Collapse.getInstance($collapse[0]);
            if (inst) { inst.hide(); }
          }
        });
      }());


      /* ==================================================================
         05. SCROLLSPY — highlight the nav link for the section in view
         ================================================================== */
      (function initScrollSpy() {
        var $links   = $('#mainNav .nav-link[href^="#"]');
        var sections = [];

        $links.each(function () {
          var $sec = $($(this).attr('href'));
          if ($sec.length) { sections.push({ $link: $(this), $sec: $sec }); }
        });
        if (!sections.length) { return; }

        var update = rafThrottle(function () {
          var navH = $('#mainNav').outerHeight() || 80;
          var pos  = $win.scrollTop() + navH + 24;
          var current = sections[0];

          $.each(sections, function (_, item) {
            if (item.$sec.offset().top <= pos) { current = item; }
          });

          // Pin the last link once the page bottom is reached.
          if ($win.scrollTop() + $win.height() >= $doc.height() - 4) {
            current = sections[sections.length - 1];
          }

          $links.removeClass('active');
          current.$link.addClass('active');
        });

        $win.on('scroll resize', update);
        update();
      }());


      /* ==================================================================
         06. SCROLL-REVEAL ENGINE
         Elements carry data-reveal="fade-up|fade-down|fade-left|fade-right|
         zoom-in" and an optional data-reveal-delay in milliseconds.
         ================================================================== */
      (function initReveal() {
        var $items = $('[data-reveal]');
        if (!$items.length) { return; }

        // No IntersectionObserver (or motion is reduced) → show everything.
        if (reduceMotion || !('IntersectionObserver' in window)) {
          $items.addClass('is-visible');
          return;
        }

        var observer = new IntersectionObserver(function (entries, obs) {
          entries.forEach(function (entry) {
            if (!entry.isIntersecting) { return; }

            var el    = entry.target;
            var delay = parseInt(el.getAttribute('data-reveal-delay'), 10) || 0;

            window.setTimeout(function () { el.classList.add('is-visible'); }, delay);
            obs.unobserve(el); // reveal once, then stop watching
          });
        }, {
          threshold: 0.08,
          // Negative bottom margin fires slightly before the element is fully
          // in view; anything already on screen at load resolves immediately.
          rootMargin: '0px 0px -10% 0px'
        });

        $items.each(function () { observer.observe(this); });
      }());


      /* ==================================================================
         07. ANIMATED COUNTERS
         Counts up the first time the stats strip enters the viewport.
         data-pad="2" zero-pads the result (5 → "05").
         ================================================================== */
      (function initCounters() {
        var $counters = $('.counter');
        if (!$counters.length) { return; }

        function pad(value, width) {
          var s = String(value);
          while (width && s.length < width) { s = '0' + s; }
          return s;
        }

        var DURATION = 1600;

        function run($el) {
          var target = parseInt($el.attr('data-count'), 10) || 0;
          var width  = parseInt($el.attr('data-pad'), 10) || 0;
          var done   = false;

          function settle() { done = true; $el.text(pad(target, width)); }

          if (reduceMotion) { settle(); return; }

          $({ n: 0 }).animate({ n: target }, {
            duration: DURATION,
            easing: 'swing',
            step: function () { if (!done) { $el.text(pad(Math.floor(this.n), width)); } },
            complete: settle
          });

          /* Safety net. jQuery's tween is driven by requestAnimationFrame,
             which browsers pause in background tabs and throttle in some
             embedded/headless contexts. Without this the figure could sit on
             "0" indefinitely — and "0 Industries Served" reads as a factual
             claim, not an unfinished animation. The `done` flag also stops a
             late-resuming tween from winding the number back down. */
          window.setTimeout(settle, DURATION + 600);
        }

        if (!('IntersectionObserver' in window)) {
          $counters.each(function () { run($(this)); });
          return;
        }

        var obs = new IntersectionObserver(function (entries, o) {
          entries.forEach(function (entry) {
            if (entry.isIntersecting) {
              run($(entry.target));
              o.unobserve(entry.target);
            }
          });
        }, { threshold: 0.4 });

        $counters.each(function () { obs.observe(this); });
      }());


      /* ==================================================================
         08. "HOW IT WORKS" CONNECTOR LINE
         Draws the dashed rule between the four steps when it scrolls in.
         ================================================================== */
      (function initHowLine() {
        var $row = $('.how__row');
        if (!$row.length) { return; }

        if (reduceMotion || !('IntersectionObserver' in window)) {
          $row.addClass('is-drawn');
          return;
        }

        var obs = new IntersectionObserver(function (entries, o) {
          entries.forEach(function (entry) {
            if (entry.isIntersecting) {
              $(entry.target).addClass('is-drawn');
              o.unobserve(entry.target);
            }
          });
        }, { threshold: 0.3 });

        $row.each(function () { obs.observe(this); });
      }());


      /* ==================================================================
         09. PRODUCT TABS — sliding pill
         The pill is a single element moved behind whichever Bootstrap tab
         button is active, rather than animating each button's background.
         ================================================================== */
      (function initTabPill() {
        var $tabs = $('.prod-tabs');
        if (!$tabs.length) { return; }

        var $pill = $tabs.find('.prod-tabs__pill');

        function movePill() {
          var $active = $tabs.find('.prod-tabs__btn.active');
          if (!$active.length) { return; }

          var offset = $active.position().left - parseFloat($tabs.css('padding-left'));
          $pill.css({
            width: $active.outerWidth() + 'px',
            transform: 'translateX(' + offset + 'px)'
          });
        }

        // Bootstrap fires shown.bs.tab once the pane transition finishes.
        $tabs.on('shown.bs.tab', '.prod-tabs__btn', movePill);
        $win.on('resize', rafThrottle(movePill));

        movePill();
        // Re-measure once webfonts land, since button widths shift with them.
        if (document.fonts && document.fonts.ready) {
          document.fonts.ready.then(movePill);
        }
        $win.on('load', movePill);
      }());


      /* ==================================================================
         10. HERO CAROUSEL
         Bootstrap drives the slides; this re-triggers the per-slide text
         animation and adds keyboard arrow support.
         ================================================================== */
      (function initHero() {
        var el = document.getElementById('heroCarousel');
        if (!el) { return; }

        var carousel = bootstrap.Carousel.getOrCreateInstance(el);
        var $bgSlides = $('.hero__media .hero__slide');

        if (reduceMotion) { carousel.pause(); }

        /* Cross-fade the background banner in step with the headline.
           There are more headline slides than banners, so the banner index
           wraps — one timer drives both, so they can never fall out of sync. */
        function syncBackground(index) {
          if (!$bgSlides.length) { return; }
          var i = ((index % $bgSlides.length) + $bgSlides.length) % $bgSlides.length;
          $bgSlides.removeClass('is-active').eq(i).addClass('is-active');
        }

        $(el).on('slid.bs.carousel', function (e) {
          // Restart the CSS entrance animation on the incoming headline.
          var $active = $(el).find('.carousel-item.active');
          $active.find('.hero__title, .hero__sub').each(function () {
            this.style.animation = 'none';
            void this.offsetWidth;      // force reflow so the animation replays
            this.style.animation = '';
          });

          syncBackground(typeof e.to === 'number' ? e.to : $active.index());
        });

        $doc.on('keydown', function (e) {
          if ($win.scrollTop() > $(el).offset().top + el.offsetHeight) { return; }
          if (e.key === 'ArrowLeft')  { carousel.prev(); }
          if (e.key === 'ArrowRight') { carousel.next(); }
        });
      }());


      /* ==================================================================
         11. ABOUT IMAGE PARALLAX
         Feeds a --shift custom property that style.css applies as a
         translate. Desktop only, and skipped when motion is reduced.
         ================================================================== */
      (function initParallax() {
        var $img = $('.about-visual__img');
        if (!$img.length || reduceMotion) { return; }

        var update = rafThrottle(function () {
          if ($win.width() < 992) { $img.css('--shift', '0px'); return; }

          var rect = $img[0].getBoundingClientRect();
          if (rect.bottom < 0 || rect.top > $win.height()) { return; }

          /* -1 → 1 as the element travels through the viewport. Kept to ±12px:
             the image sits inside a fixed outline ring (.about-visual::before)
             whose position does not move with a transform, so a larger travel
             would visibly decentre the image within its own frame. */
          var progress = (rect.top + rect.height / 2 - $win.height() / 2) / $win.height();
          $img.css('--shift', (progress * -12).toFixed(1) + 'px');
        });

        $win.on('scroll resize', update);
        update();
      }());


      /* ==================================================================
         12. VIDEO MODAL — stop playback when the dialog closes
         ================================================================== */
      (function initVideoModal() {
        var modal = document.getElementById('videoModal');
        var video = document.getElementById('brandVideo');
        if (!modal || !video) { return; }

        modal.addEventListener('shown.bs.modal', function () {
          var playing = video.play();
          if (playing && playing.catch) { playing.catch(function () { /* autoplay blocked — fine */ }); }
        });

        modal.addEventListener('hidden.bs.modal', function () {
          video.pause();
          video.currentTime = 0;
        });
      }());


      /* ==================================================================
         13. BACK TO TOP
         ================================================================== */
      (function initBackToTop() {
        var $btn = $('#backToTop');

        var update = rafThrottle(function () {
          $btn.toggleClass('is-shown', $win.scrollTop() > 500);
        });

        $win.on('scroll', update);
        update();

        $btn.on('click', function () {
          $('html, body').stop().animate({ scrollTop: 0 }, reduceMotion ? 0 : 700, 'swing');
        });
      }());


      /* ==================================================================
         14. MAGNETIC BUTTONS
         Pointer-following nudge on fine-pointer devices only, so touch
         users never get a stuck transform.
         ================================================================== */
      (function initMagnetic() {
        if (reduceMotion) { return; }
        if (!(window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches)) { return; }

        $doc.on('mousemove', '.btn-zb', function (e) {
          var rect = this.getBoundingClientRect();
          var dx = (e.clientX - rect.left - rect.width  / 2) * 0.22;
          var dy = (e.clientY - rect.top  - rect.height / 2) * 0.32;
          this.style.transform = 'translate(' + dx.toFixed(1) + 'px, ' + (dy - 3).toFixed(1) + 'px)';
        });

        $doc.on('mouseleave', '.btn-zb', function () {
          this.style.transform = '';
        });
      }());


      /* ==================================================================
         15. CONTACT FORM VALIDATION
         Client-side only — this is a static build with no backend. The
         success path says so explicitly rather than implying the message
         was delivered, and points at the real phone/email instead.
         ================================================================== */
      (function initContactForm() {
        var $form   = $('#contactForm');
        if (!$form.length) { return; }

        var $status = $('#formStatus');

        var emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
        var phoneRe = /^[+\d][\d\s\-().]{7,19}$/;

        function setInvalid($field, invalid) {
          $field.toggleClass('is-invalid', invalid);
          if (invalid) {
            $field.removeClass('field-shake');
            void $field[0].offsetWidth;      // restart the shake animation
            $field.addClass('field-shake');
          }
        }

        function validateField($field) {
          var val = $.trim($field.val());
          var id  = $field.attr('id');
          var ok  = val.length > 0;

          if (ok && id === 'email')   { ok = emailRe.test(val); }
          if (ok && id === 'phone')   { ok = phoneRe.test(val); }
          if (ok && id === 'message') { ok = val.length >= 10; }

          return ok;
        }

        // Clear the error state as soon as the visitor fixes the field.
        $form.on('input change', '.form-control, .form-select', function () {
          var $f = $(this);
          if ($f.hasClass('is-invalid') && validateField($f)) {
            $f.removeClass('is-invalid field-shake');
          }
        });

        $form.on('submit', function (e) {
          e.preventDefault();

          var firstBad = null;

          $form.find('[required]').each(function () {
            var $f = $(this);
            var ok = validateField($f);
            setInvalid($f, !ok);
            if (!ok && !firstBad) { firstBad = $f; }
          });

          if (firstBad) {
            $status
              .removeClass('is-ok')
              .addClass('is-shown is-error')
              .html('Please correct the highlighted fields and try again.');
            firstBad.trigger('focus');
            return;
          }

          /* ----------------------------------------------------------------
             NO BACKEND IS WIRED UP. To make this form actually deliver mail,
             POST the values to your endpoint (or a form service) here — e.g.
                 $.post('https://your-endpoint.example/contact', $form.serialize())
             and replace the notice below with a real confirmation.
             ---------------------------------------------------------------- */
          $status
            .removeClass('is-error')
            .addClass('is-shown is-ok')
            .html(
              '<strong>All set — your details look good.</strong><br>' +
              'This is a static build with no server attached, so nothing was sent. ' +
              'Reach the team directly on <a href="tel:+919121208058">+91 9121208058</a> ' +
              'or <a href="mailto:support@zibomo.in">support@zibomo.in</a>.'
            );

          $form.find('.form-control, .form-select').removeClass('is-invalid field-shake');
        });
      }());


    } catch (err) {
      /* Any failure above unhides everything so no content is lost. */
      $html.removeClass('js');
      $('#preloader').remove();
      $('html').css('overflow', '');
      if (window.console && console.error) {
        console.error('[Zibomo] init failed — falling back to static page.', err);
      }
    }

  });

}(jQuery));
