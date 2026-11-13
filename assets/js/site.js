/* Comportamentos gerais do site público. */
(function () {
  'use strict';

  var toggle = document.querySelector('.nav-toggle');
  var nav = document.getElementById('nav-principal');

  if (toggle && nav) {
    toggle.addEventListener('click', function () {
      var open = nav.getAttribute('data-open') === 'true';
      nav.setAttribute('data-open', String(!open));
      toggle.setAttribute('aria-expanded', String(!open));
    });

    nav.addEventListener('click', function (ev) {
      if (ev.target.tagName === 'A' && window.innerWidth <= 900) {
        nav.setAttribute('data-open', 'false');
        toggle.setAttribute('aria-expanded', 'false');
      }
    });
  }

  // Confirmação antes de ações destrutivas
  document.addEventListener('click', function (ev) {
    var el = ev.target.closest('[data-confirm]');
    if (el && !window.confirm(el.getAttribute('data-confirm'))) {
      ev.preventDefault();
      ev.stopPropagation();
    }
  }, true);
})();
