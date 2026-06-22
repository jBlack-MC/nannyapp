/* ============================================================
   Nanny-App  •  front-end behaviour
   Vanilla JS, no dependencies. Organised into small modules:
   nav, scroll-reveal, toasts, confirm-modal, form feedback, PWA.
   Animations use transform/opacity only (no layout thrash).
   ============================================================ */
(function () {
  'use strict';

  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var on = function (el, ev, fn) { el && el.addEventListener(ev, fn); };

  /* ---------- 0. Splash screen (first visit per session) ---------- */
  (function () {
    var splash = document.getElementById('splash');
    if (!splash) return;
    var seen = false;
    try { seen = sessionStorage.getItem('na_splash') === '1'; } catch (e) {}
    if (seen || reduceMotion) {            // skip the hold if already shown / reduced motion
      splash.parentNode.removeChild(splash);
      return;
    }
    var done = function () {
      splash.classList.add('hide');
      try { sessionStorage.setItem('na_splash', '1'); } catch (e) {}
      setTimeout(function () { if (splash.parentNode) splash.parentNode.removeChild(splash); }, 600);
    };
    // Hold ~2s but never longer than load+grace.
    var t = setTimeout(done, 2000);
    on(window, 'load', function () { clearTimeout(t); setTimeout(done, 600); });
  })();

  /* ---------- 1. Fixed-nav: dark-glass when scrolled (or no hero) ---------- */
  var topbar = document.getElementById('topbar');
  if (topbar) {
    var hasHero = !!document.querySelector('.hero, .home-hero');
    var onScroll = function () { topbar.classList.toggle('scrolled', !hasHero || window.scrollY > 80); };
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
  }

  /* ---------- 2. Mobile hamburger menu ---------- */
  var toggle = document.getElementById('navToggle');
  var nav = document.getElementById('primary-nav');
  if (toggle && nav) {
    var setNav = function (open) {
      nav.classList.toggle('open', open);
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      // Lock background scroll on mobile when drawer is open
      document.body.style.overflow = open ? 'hidden' : '';
    };
    on(toggle, 'click', function () { setNav(!nav.classList.contains('open')); });
    // Close on link tap, outside click, resize past breakpoint, or Escape
    nav.querySelectorAll('a').forEach(function (a) { on(a, 'click', function () { setNav(false); }); });
    on(document, 'click', function (e) {
      if (nav.classList.contains('open') && !nav.contains(e.target) && !toggle.contains(e.target)) setNav(false);
    });
    on(document, 'keydown', function (e) { if (e.key === 'Escape') setNav(false); });
    // Auto-close if window resizes above the mobile breakpoint (768px)
    on(window, 'resize', function () {
      if (window.innerWidth > 768 && nav.classList.contains('open')) setNav(false);
    });
  }

  /* ---------- 3. Active navigation state ---------- */
  (function () {
    if (!nav) return;
    var here = location.pathname.replace(/\/index\.php$/, '/');
    nav.querySelectorAll('a').forEach(function (a) {
      var path = a.pathname.replace(/\/index\.php$/, '/');
      if (path === here) a.classList.add('active');
    });
  })();

  /* ---------- 3a. Data-driven percentage fills (no inline style attrs) ---------- */
  (function () {
    function clampPct(v) {
      var n = parseFloat(v);
      if (!isFinite(n)) return 0;
      if (n < 0) return 0;
      if (n > 100) return 100;
      return n;
    }

    document.querySelectorAll('[data-height-pct]').forEach(function (el) {
      el.style.height = clampPct(el.getAttribute('data-height-pct')) + '%';
    });

    document.querySelectorAll('[data-width-pct]').forEach(function (el) {
      el.style.width = clampPct(el.getAttribute('data-width-pct')) + '%';
    });
  })();

  /* ---------- 4. Scroll reveal (IntersectionObserver) ---------- */
  (function () {
    if (reduceMotion || !('IntersectionObserver' in window)) {
      // No JS animation: make sure nothing stays hidden.
      document.querySelectorAll('.reveal-item').forEach(function (el) { el.classList.add('is-visible'); });
      return;
    }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) { en.target.classList.add('is-visible'); io.unobserve(en.target); }
      });
    }, { threshold: 0.08, rootMargin: '0px 0px -40px 0px' });

    // Whole-section reveals.
    document.querySelectorAll('main .section, main .hero, main > .card').forEach(function (t, i) {
      t.classList.add('reveal');
      t.style.transitionDelay = Math.min(i * 60, 240) + 'ms';
      io.observe(t);
    });

    // Per-card staggered reveals: delay is relative to each card's own row.
    document.querySelectorAll('main .grid, main .stat-band').forEach(function (group) {
      group.querySelectorAll('.reveal-item').forEach(function (el, i) {
        el.style.transitionDelay = Math.min(i * 110, 440) + 'ms';
        io.observe(el);
      });
    });
    // Stand-alone reveal-items (not inside a grid).
    document.querySelectorAll('main .reveal-item').forEach(function (el) {
      if (!el.style.transitionDelay) io.observe(el);
    });
  })();

  /* ---------- 4a. Animated number counters ---------- */
  (function () {
    var counters = document.querySelectorAll('[data-count]');
    if (!counters.length) return;
    if (reduceMotion || !('IntersectionObserver' in window)) {
      counters.forEach(function (el) { el.textContent = format(el, +el.getAttribute('data-count')); });
      return;
    }
    function format(el, val) {
      var dec = +(el.getAttribute('data-decimals') || 0);
      if (dec) val = val / Math.pow(10, dec);
      var s = dec ? val.toFixed(dec) : Math.round(val).toLocaleString('en-US');
      return s + (el.getAttribute('data-suffix') || '');
    }
    function run(el) {
      var target = +el.getAttribute('data-count'), start = null, dur = 1500;
      function step(ts) {
        if (!start) start = ts;
        var p = Math.min((ts - start) / dur, 1);
        var eased = 1 - Math.pow(1 - p, 3); // easeOutCubic
        el.textContent = format(el, target * eased);
        if (p < 1) requestAnimationFrame(step);
        else el.textContent = format(el, target);
      }
      requestAnimationFrame(step);
    }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) { run(en.target); io.unobserve(en.target); }
      });
    }, { threshold: 0.5 });
    counters.forEach(function (el) { io.observe(el); });
  })();

  /* ---------- 4b. Testimonial carousel (auto-advancing, arrows, swipe) ---------- */
  (function () {
    var vp = document.getElementById('testimonials');
    if (!vp) return;
    var track = vp.querySelector('.t-track');
    var cards = track ? track.querySelectorAll('.tcard') : [];
    if (!track || cards.length < 2) return;

    var controls = document.querySelector('.t-controls[data-for="' + vp.id + '"]');
    var dots = controls ? controls.querySelector('.t-dots') : null;

    // perView follows the CSS breakpoints (1 / 2 / 3 up).
    function perView() {
      if (window.matchMedia('(min-width:1100px)').matches) return 3;
      if (window.matchMedia('(min-width:760px)').matches) return 2;
      return 1;
    }
    function pages() { return Math.max(1, cards.length - perView() + 1); }
    var idx = 0, timer = null;

    function buildDots() {
      if (!dots) return;
      dots.innerHTML = '';
      for (var i = 0; i < pages(); i++) {
        var b = document.createElement('button');
        b.className = 't-dot' + (i === idx ? ' active' : '');
        b.setAttribute('aria-label', 'Go to slide ' + (i + 1));
        (function (n) { on(b, 'click', function () { go(n); restart(); }); })(i);
        dots.appendChild(b);
      }
    }
    function go(n) {
      var total = pages();
      idx = ((n % total) + total) % total;        // wrap both directions (infinite loop)
      var step = cards[0].getBoundingClientRect().width +
                 parseFloat(getComputedStyle(track).columnGap || 24);
      track.style.transform = 'translateX(' + (-idx * step) + 'px)';
      if (dots) dots.querySelectorAll('.t-dot').forEach(function (d, i) {
        d.classList.toggle('active', i === idx);
      });
    }
    function next() { go(idx + 1); }
    function prev() { go(idx - 1); }

    var autoplay = +(vp.getAttribute('data-autoplay') || 5000);
    function start() { if (!reduceMotion && autoplay && !timer) timer = setInterval(next, autoplay); }
    function stop() { if (timer) { clearInterval(timer); timer = null; } }
    function restart() { stop(); start(); }

    // Pause while the reader is engaged.
    on(vp, 'mouseenter', stop); on(vp, 'mouseleave', start);
    if (controls) { on(controls, 'mouseenter', stop); on(controls, 'mouseleave', start); }
    on(vp, 'focusin', stop); on(vp, 'focusout', start);
    on(document, 'visibilitychange', function () { document.hidden ? stop() : start(); });

    // Arrows.
    if (controls) {
      on(controls.querySelector('[data-t="prev"]'), 'click', function () { prev(); restart(); });
      on(controls.querySelector('[data-t="next"]'), 'click', function () { next(); restart(); });
    }

    // Touch / swipe.
    var x0 = null;
    on(vp, 'touchstart', function (e) { x0 = e.touches[0].clientX; stop(); }, { passive: true });
    on(vp, 'touchend', function (e) {
      if (x0 === null) return;
      var dx = e.changedTouches[0].clientX - x0;
      if (Math.abs(dx) > 40) (dx < 0 ? next() : prev());
      x0 = null; start();
    });

    buildDots(); go(0); start();
    var rt;
    on(window, 'resize', function () {
      clearTimeout(rt);
      rt = setTimeout(function () { buildDots(); go(Math.min(idx, pages() - 1)); }, 150);
    });
  })();

  /* ---------- 4c. FAQ accordion ---------- */
  (function () {
    var items = document.querySelectorAll('.faq-item');
    if (!items.length) return;
    items.forEach(function (item) {
      var q = item.querySelector('.faq-q');
      var a = item.querySelector('.faq-a');
      if (!q || !a) return;
      on(q, 'click', function () {
        var isOpen = item.classList.contains('open');
        // Close siblings for a clean single-open accordion.
        items.forEach(function (other) {
          if (other !== item) {
            other.classList.remove('open');
            var oa = other.querySelector('.faq-a'), oq = other.querySelector('.faq-q');
            if (oa) oa.style.maxHeight = null;
            if (oq) oq.setAttribute('aria-expanded', 'false');
          }
        });
        item.classList.toggle('open', !isOpen);
        q.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
        a.style.maxHeight = isOpen ? null : a.scrollHeight + 'px';
      });
    });
  })();

  /* ---------- 4d. Gentle parallax on cover backgrounds ---------- */
  (function () {
    if (reduceMotion) return;
    var layers = [].slice.call(document.querySelectorAll('.hero-bg, .final-cta-bg'));
    if (!layers.length) return;
    var ticking = false;
    function update() {
      var vh = window.innerHeight;
      layers.forEach(function (el) {
        var r = el.parentNode.getBoundingClientRect();
        if (r.bottom < 0 || r.top > vh) return;     // off-screen, skip
        // shift relative to the section's position in the viewport
        var offset = (r.top + r.height / 2 - vh / 2) * -0.06;
        el.style.transform = 'translate3d(0,' + offset.toFixed(1) + 'px,0)';
      });
      ticking = false;
    }
    on(window, 'scroll', function () {
      if (!ticking) { ticking = true; requestAnimationFrame(update); }
    }, { passive: true });
    on(window, 'resize', update);
    update();
  })();

  /* ---------- 4e. Scroll-to-top button ---------- */
  (function () {
    var btn = document.getElementById('scrollTop');
    if (!btn) return;
    var toggle = function () { btn.classList.toggle('show', window.scrollY > 600); };
    toggle();
    on(window, 'scroll', toggle, { passive: true });
    on(btn, 'click', function () {
      window.scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' });
    });
  })();

  /* ---------- 4ea. Gallery lightbox ---------- */
  (function () {
    var active = null;
    function closeBox() {
      if (!active) return;
      active.remove();
      active = null;
      document.body.classList.remove('lightbox-open');
    }

    on(document, 'click', function (e) {
      var trigger = e.target.closest('[data-toggle="lightbox"]');
      if (!trigger) return;
      e.preventDefault();
      var href = trigger.getAttribute('href');
      if (!href) return;

      closeBox();
      var box = document.createElement('div');
      box.className = 'simple-lightbox';
      box.innerHTML = '<button type="button" class="slb-close" aria-label="Close image">×</button>' +
                      '<img src="" alt="Gallery image">';
      box.querySelector('img').src = href;
      document.body.appendChild(box);
      document.body.classList.add('lightbox-open');
      active = box;

      on(box, 'click', function (ev) {
        if (ev.target === box || ev.target.classList.contains('slb-close')) closeBox();
      });
    });

    on(document, 'keydown', function (e) {
      if (e.key === 'Escape') closeBox();
    });
  })();

  /* ---------- 4f. Newsletter subscribe (client-side success state) ---------- */
  (function () {
    var form = document.querySelector('.subscribe-form');
    if (!form) return;
    on(form, 'submit', function (e) {
      e.preventDefault();
      var input = form.querySelector('input[type="email"]');
      if (input && (!input.value || !input.checkValidity())) {
        input.focus();
        if (window.showToast) showToast('Please enter a valid email address.', 'error');
        return;
      }
      // Mark the submit button as handled so the generic loader (below) skips it.
      var btn = form.querySelector('button[type="submit"]');
      if (btn) btn.dataset.busy = '1';
      if (window.showToast) showToast('Thank you for subscribing! Please check your inbox to confirm.', 'success');
      form.reset();
      if (btn) setTimeout(function () { delete btn.dataset.busy; }, 0);
    });
  })();

  /* ---------- 4g. Booking cost estimator + quick durations ---------- */
  (function () {
    var form = document.getElementById('bookingForm');
    if (!form) return;
    var rate = parseFloat(form.getAttribute('data-rate')) || 0;
    var dur  = form.querySelector('#bk-dur');
    var out  = document.getElementById('estCost');
    var btns = form.querySelectorAll('.dur-btn');
    if (!dur) return;
    function fmt(n) { return 'R' + n.toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function update() {
      var h = parseFloat(dur.value) || 0;
      if (out) out.textContent = fmt(h * rate);
      btns.forEach(function (b) { b.classList.toggle('active', parseFloat(b.getAttribute('data-hours')) === h); });
    }
    btns.forEach(function (b) {
      on(b, 'click', function () { dur.value = b.getAttribute('data-hours'); update(); });
    });
    on(dur, 'input', update);
    update();
  })();

  /* ---------- 5. Toast notifications ---------- */
  var stack = null;
  function toastStack() {
    if (!stack) {
      stack = document.createElement('div');
      stack.className = 'toast-stack';
      stack.setAttribute('aria-live', 'polite');
      document.body.appendChild(stack);
    }
    return stack;
  }
  var ICONS = { success: '✓', error: '⚠', info: 'ℹ' };
  function showToast(message, type) {
    type = type || 'info';
    var t = document.createElement('div');
    t.className = 'toast toast--' + type;
    t.setAttribute('role', 'status');
    t.innerHTML = '<span class="toast-ico">' + (ICONS[type] || ICONS.info) + '</span>' +
                  '<span class="toast-msg"></span>' +
                  '<button class="toast-close" aria-label="Dismiss">×</button>';
    t.querySelector('.toast-msg').textContent = message;
    toastStack().appendChild(t);
    var remove = function () {
      t.classList.add('leaving');
      setTimeout(function () { t.remove(); }, 220);
    };
    on(t.querySelector('.toast-close'), 'click', remove);
    setTimeout(remove, 4500);
  }
  window.showToast = showToast; // expose for any inline use

  // Lift server-rendered session flashes ([data-toast]) into animated toasts.
  document.querySelectorAll('.flash[data-toast]').forEach(function (el) {
    var type = (el.className.match(/flash-(\w+)/) || [])[1];
    type = type === 'success' ? 'success' : type === 'error' ? 'error' : 'info';
    showToast(el.textContent.trim(), type);
    el.remove();
  });

  /* ---------- 6. Animated confirm modal (replaces window.confirm) ---------- */
  var pending = null; // element awaiting confirmation
  var overlay = null;
  function buildModal() {
    overlay = document.createElement('div');
    overlay.className = 'modal-overlay';
    overlay.innerHTML =
      '<div class="modal" role="dialog" aria-modal="true" aria-labelledby="cm-title">' +
        '<h3 id="cm-title">Please confirm</h3>' +
        '<p class="modal-text"></p>' +
        '<div class="modal-actions">' +
          '<button class="btn btn-sm" data-cm="cancel">Cancel</button>' +
          '<button class="btn btn-sm btn-danger" data-cm="ok">Confirm</button>' +
        '</div>' +
      '</div>';
    document.body.appendChild(overlay);
    on(overlay, 'click', function (e) { if (e.target === overlay) close(); });
    on(overlay.querySelector('[data-cm="cancel"]'), 'click', close);
    on(overlay.querySelector('[data-cm="ok"]'), 'click', confirmAction);
    on(document, 'keydown', function (e) {
      if (!overlay.classList.contains('open')) return;
      if (e.key === 'Escape') close();
      if (e.key === 'Enter') confirmAction();
    });
  }
  function openModal(text) {
    if (!overlay) buildModal();
    overlay.querySelector('.modal-text').textContent = text || 'Are you sure you want to continue?';
    overlay.classList.add('open');
    overlay.querySelector('[data-cm="ok"]').focus();
  }
  function close() { if (overlay) overlay.classList.remove('open'); pending = null; }
  function confirmAction() {
    var el = pending; close();
    if (!el) return;
    if (el.tagName === 'A' && el.href) { window.location.href = el.href; return; }
    // Submit the owning form *with this button as the submitter* so its
    // name/value (e.g. action=delete) is included.
    var form = el.form || el.closest('form');
    if (form) {
      if (typeof form.requestSubmit === 'function') { form.requestSubmit(el); }
      else { var h = document.createElement('input'); h.type = 'hidden';
        h.name = el.name; h.value = el.value; form.appendChild(h); form.submit(); }
    }
  }
  document.addEventListener('click', function (e) {
    var el = e.target.closest('[data-confirm]');
    if (!el) return;
    e.preventDefault();
    pending = el;
    openModal(el.getAttribute('data-confirm'));
  });

  /* ---------- 7. Button loading feedback on submit ---------- */
  document.querySelectorAll('form').forEach(function (form) {
    on(form, 'submit', function () {
      // skip forms that go through the confirm modal (handled above)
      var btn = form.querySelector('button[type=submit], button:not([type])');
      if (btn && !btn.hasAttribute('data-confirm') && !btn.dataset.busy) {
        btn.dataset.busy = '1';
        setTimeout(function () { btn.classList.add('is-loading'); }, 0);
      }
    });
  });

  /* ---------- 8. PWA service-worker registration ---------- */
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      var base = document.querySelector('link[rel="manifest"]');
      var scope = base ? base.href.replace('manifest.webmanifest', '') : '/';
      navigator.serviceWorker.register(scope + 'service-worker.js', { scope: scope })
        .catch(function (e) { console.warn('SW registration failed:', e); });
    });
  }

  /* ---------- 9. Dark mode toggle ---------- */
  (function () {
    // Apply saved or system preference before paint to avoid flash.
    var saved = null;
    try { saved = localStorage.getItem('na_theme'); } catch (e) {}
    var pref = saved || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    document.documentElement.setAttribute('data-theme', pref);

    var btn = document.getElementById('darkToggle');
    if (!btn) return;
    function applyTheme(t) {
      document.documentElement.setAttribute('data-theme', t);
      try { localStorage.setItem('na_theme', t); } catch (e) {}
    }
    on(btn, 'click', function () {
      var current = document.documentElement.getAttribute('data-theme');
      applyTheme(current === 'dark' ? 'light' : 'dark');
    });
  })();

  /* ---------- 10. Save / unsave nanny (AJAX toggle) ---------- */
  (function () {
    document.querySelectorAll('[data-save-nanny]').forEach(function (btn) {
      on(btn, 'click', function (e) {
        e.preventDefault();
        var nannyId = btn.getAttribute('data-save-nanny');
        var saved   = btn.classList.contains('saved');
        var action  = saved ? 'unsave' : 'save';
        var base    = btn.getAttribute('data-base') || '/nannyapp';

        btn.style.pointerEvents = 'none';
        fetch(base + '/parent/save_nanny.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'nanny_id=' + encodeURIComponent(nannyId)
               + '&action=' + encodeURIComponent(action)
               + '&csrf=' + encodeURIComponent(btn.getAttribute('data-csrf') || '')
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          btn.style.pointerEvents = '';
          if (data.ok) {
            btn.classList.toggle('saved', action === 'save');
            var label = btn.querySelector('.save-label');
            if (label) label.textContent = action === 'save' ? 'Saved' : 'Save';
            if (window.showToast) showToast(data.message, 'success');
          } else {
            if (window.showToast) showToast(data.message || 'Please log in as a parent to save.', 'error');
            btn.style.pointerEvents = '';
          }
        })
        .catch(function () {
          btn.style.pointerEvents = '';
          if (window.showToast) showToast('Could not update saved nannies.', 'error');
        });
      });
    });
  })();

  /* ---------- 11. Availability row toggle (nanny/availability.php) ---------- */
  (function () {
    document.querySelectorAll('.avail-row input[type="checkbox"]').forEach(function (cb) {
      function sync() {
        var row = cb.closest('.avail-row');
        if (!row) return;
        row.classList.toggle('unavail', !cb.checked);
        row.querySelectorAll('input:not([type="checkbox"])').forEach(function (inp) {
          inp.disabled = !cb.checked;
        });
      }
      sync();
      on(cb, 'change', sync);
    });
  })();

  /* ---------- 12. Earnings bar chart animation ---------- */
  (function () {
    var bars = document.querySelectorAll('.e-bar[data-pct]');
    if (!bars.length) return;
    if (reduceMotion) {
      bars.forEach(function (b) { b.style.height = b.getAttribute('data-pct') + '%'; });
      return;
    }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) {
          en.target.style.height = en.target.getAttribute('data-pct') + '%';
          io.unobserve(en.target);
        }
      });
    }, { threshold: 0.3 });
    bars.forEach(function (b) { b.style.height = '0'; b.style.transition = 'height .8s cubic-bezier(.4,0,.2,1)'; io.observe(b); });
  })();

  /* ---------- 13. Profile image preview ---------- */
  (function () {
    document.querySelectorAll('input[type="file"][data-preview]').forEach(function (inp) {
      on(inp, 'change', function () {
        var tgt = document.getElementById(inp.getAttribute('data-preview'));
        if (!tgt || !inp.files || !inp.files[0]) return;
        var reader = new FileReader();
        reader.onload = function (e) {
          if (tgt.tagName === 'IMG') { tgt.src = e.target.result; }
          else { tgt.style.backgroundImage = 'url(' + e.target.result + ')'; }
        };
        reader.readAsDataURL(inp.files[0]);
      });
    });
  })();

  /* ---------- 14. User profile dropdown ---------- */
  (function () {
    var toggle = document.getElementById('navUserToggle');
    var menu   = document.getElementById('navUserMenuPanel');
    if (!toggle || !menu) return;
    function isOpen() { return toggle.getAttribute('aria-expanded') === 'true'; }
    function openMenu()  { toggle.setAttribute('aria-expanded', 'true');  menu.classList.add('open'); }
    function closeMenu() { toggle.setAttribute('aria-expanded', 'false'); menu.classList.remove('open'); }
    on(toggle, 'click', function (e) { e.stopPropagation(); isOpen() ? closeMenu() : openMenu(); });
    on(document, 'click', function (e) {
      if (!toggle.contains(e.target) && !menu.contains(e.target)) closeMenu();
    });
    on(document, 'keydown', function (e) { if (e.key === 'Escape' && isOpen()) closeMenu(); });
    // On mobile the drawer close already hides the whole nav; sync aria state.
    on(document, 'click', function (e) {
      if (e.target.closest('.menu-btn')) closeMenu();
    });
  })();
})();
