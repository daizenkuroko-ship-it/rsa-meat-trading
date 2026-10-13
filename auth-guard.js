// RSA Meat Trading - page guard + ONE shared sidebar for every page
// (operations pages AND owner pages), so the layout never changes between pages.
//
// The real protection is on the server (api/*.php and the owner/*.php pages check the
// role on every request). This script sends signed-out visitors to the right login page
// and draws the same sidebar everywhere, showing each role only what it may use:
//   Both roles see the SAME sidebar (same design, same buttons, same order).
//   admin : the owner-only buttons are shown greyed out with a lock and cannot be clicked
//   owner : every button works
(function () {
  'use strict';

  var inOwner = /\/owner\/[^\/]*$/i.test(location.pathname);
  var base = inOwner ? '../' : '';
  var loginPage = inOwner ? 'index.php' : 'index.html';
  var file = (location.pathname.split('/').pop() || '').toLowerCase();
  if (!file) file = inOwner ? 'dashboard.php' : 'dashboard.html';
  var pageKey = (inOwner ? 'owner/' : '') + file;
  var ownerOnlyPage = pageKey === 'reports.html';

  /* ---- keep the page hidden until we know who is looking at it ---- */
  var hide = document.createElement('style');
  hide.id = 'authHide';
  hide.textContent = 'html{visibility:hidden}';
  document.head.appendChild(hide);

  function reveal() {
    var h = document.getElementById('authHide');
    if (h) h.remove();
  }

  /* ---- one sidebar look for all pages (beats each page's own CSS) ---- */
  var css = document.createElement('style');
  css.id = 'sharedSidebarCss';
  css.textContent = [
    'aside.sidebar{position:fixed !important;left:0 !important;top:0 !important;width:220px !important;height:100vh !important;z-index:1000;background:#292929 !important;color:#fff;display:flex !important;flex-direction:column !important;overflow:hidden !important;padding:0 !important;margin:0 !important;box-sizing:border-box !important;font-family:Arial,Helvetica,sans-serif !important}',
    'aside.sidebar *{box-sizing:border-box;font-family:Arial,Helvetica,sans-serif}',
    'aside.sidebar .brand{padding:18px 20px !important;margin:0 !important;font-size:16px !important;line-height:1.3 !important;white-space:nowrap;font-weight:bold !important;color:#fff !important;border-bottom:1px solid #444 !important;flex:0 0 auto}',
    'aside.sidebar .side-user{flex:0 0 auto;padding:10px 20px;font-size:12px;color:#aaa;border-bottom:1px solid #3a3a3a;line-height:1.4}',
    'aside.sidebar .side-user strong{color:#fff;font-weight:600;font-size:13px}',
    'aside.sidebar .nav{padding:10px 10px !important;flex:1 1 auto !important;min-height:0;overflow-y:auto !important;scrollbar-width:thin;scrollbar-color:#555 transparent}',
    'aside.sidebar .nav::-webkit-scrollbar{width:6px}',
    'aside.sidebar .nav::-webkit-scrollbar-thumb{background:#555;border-radius:3px}',
    'aside.sidebar .nav a{display:flex !important;align-items:center;justify-content:space-between;padding:9px 14px !important;margin:0 0 3px !important;color:#ddd !important;text-decoration:none !important;border-radius:6px !important;font-size:14px !important;line-height:1.3 !important;background:transparent;transition:.2s}',
    'aside.sidebar .nav a:hover{background:#3a3a3a !important;color:#fff !important}',
    'aside.sidebar .nav a.active{background:#e60024 !important;color:#fff !important}',
    'aside.sidebar .nav a.locked,aside.sidebar .nav a.locked:hover{color:#777 !important;background:transparent !important;cursor:not-allowed !important;opacity:.75}',
    'aside.sidebar .nav a.locked .lock{font-size:11px;margin-left:8px}',
    'aside.sidebar .nav .nav-label{padding:12px 14px 5px;font-size:11px;letter-spacing:1px;text-transform:uppercase;color:#888}',
    'aside.sidebar .logout{flex:0 0 auto;margin:0 !important;padding:10px 10px !important;border-top:1px solid #444 !important}',
    'aside.sidebar .logout a{display:block !important;padding:9px 14px !important;color:#ddd !important;text-decoration:none !important;font-size:14px !important;border-radius:6px !important}',
    'aside.sidebar .logout a:hover{background:#3a3a3a !important;color:#fff !important}',
    '.main{margin-left:220px}',
    '@media (max-width:700px){.main{margin-left:0 !important}aside.sidebar{transform:translateX(-100%);transition:transform .25s}aside.sidebar.open{transform:translateX(0)}}',
    '@media print{aside.sidebar{display:none !important}.main{margin-left:0 !important}}'
  ].join('\n');
  document.head.appendChild(css);

  var domReady = new Promise(function (resolve) {
    if (document.readyState !== 'loading') resolve();
    else document.addEventListener('DOMContentLoaded', resolve);
  });

  var meReady = fetch(base + 'api/me.php?_=' + Date.now(), { credentials: 'same-origin', cache: 'no-store' })
    .then(function (res) {
      if (res.status === 401) { location.replace(loginPage); return null; }
      return res.json();
    })
    .then(function (data) {
      if (!data || !data.success) { location.replace(loginPage); return null; }
      if (ownerOnlyPage && !data.can.reports) { location.replace('dashboard.html'); return null; }
      if (inOwner && data.role !== 'owner') { location.replace(base + 'dashboard.html'); return null; }
      return data;
    });

  Promise.all([meReady, domReady])
    .then(function (r) {
      var me = r[0];
      if (!me) return;
      window.RSA_USER = me;
      buildSidebar(me);
      if (inOwner) bindMenuToggle();
      reveal();
    })
    .catch(function () { location.replace(loginPage); });

  function esc(v) {
    return String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function buildSidebar(me) {
    var side = document.querySelector('aside.sidebar');
    if (!side) return;
    var nav = side.querySelector('.nav');
    if (!nav) return;

    var isOwner = me.role === 'owner';

    // Same links, same order, on every page, for BOTH roles. [path, label, ownerOnly]
    var links = [
      ['dashboard.html', 'Dashboard', false],
      ['orders.html', 'Orders', false],
      ['create-order.html', 'Create Order', false],
      ['customers.html', 'Customers', false],
      ['reports.html', 'Reports', false]
    ];
    var ownerLinks = [
      ['owner/dashboard.php', 'Sales Report', true],
      ['owner/products.php', 'Products &amp; Prices', true],
      ['owner/admins.php', 'Manage Admins', true],
      ['owner/audit.php', 'Audit Log', true]
    ];

    function item(l) {
      var locked = l[2] && !isOwner;
      if (locked) {
        return '<a href="#" class="locked" aria-disabled="true" tabindex="-1" title="Owner only">' +
          '<span>' + l[1] + '</span><span class="lock">&#128274;</span></a>';
      }
      return '<a href="' + base + l[0] + '"' + (l[0] === pageKey ? ' class="active"' : '') + '>' + l[1] + '</a>';
    }

    // Reports is owner-only too (server enforces it); lock it for admins.
    links[4][2] = true;

    nav.innerHTML = links.map(item).join('') +
      '<div class="nav-label">Owner</div>' + ownerLinks.map(item).join('');

    // Locked buttons do nothing when clicked.
    nav.addEventListener('click', function (e) {
      var a = e.target.closest ? e.target.closest('a.locked') : null;
      if (a) e.preventDefault();
    });

    // Who is signed in (inserted once, right under the brand).
    if (!side.querySelector('.side-user')) {
      var u = document.createElement('div');
      u.className = 'side-user';
      u.innerHTML = '<strong>' + esc(me.name) + '</strong><br>' + (me.role === 'owner' ? 'Owner' : 'Admin');
      var brand = side.querySelector('.brand');
      if (brand) brand.insertAdjacentElement('afterend', u);
      else side.insertBefore(u, nav);
    }
  }

  // Phone menu button. (Operations pages bind their own; owner pages use this.)
  function bindMenuToggle() {
    var btn = document.getElementById('menuToggle');
    var side = document.getElementById('sidebar');
    if (btn && side) btn.addEventListener('click', function () { side.classList.toggle('open'); });
  }
})();
