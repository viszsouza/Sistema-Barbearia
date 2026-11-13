    </div>
  </div>
</div>

<script>
(function () {
  var sidebar = document.getElementById('sidebar');
  var toggle  = document.getElementById('menu-toggle');
  var scrim   = document.getElementById('scrim');

  function setOpen(open) {
    sidebar.setAttribute('data-open', String(open));
    toggle.setAttribute('aria-expanded', String(open));
    scrim.hidden = !open;
  }
  toggle.addEventListener('click', function () { setOpen(sidebar.getAttribute('data-open') !== 'true'); });
  scrim.addEventListener('click', function () { setOpen(false); });

  // Modais
  document.addEventListener('click', function (ev) {
    var open = ev.target.closest('[data-modal-open]');
    if (open) {
      var dlg = document.getElementById(open.dataset.modalOpen);
      if (dlg) {
        if (open.dataset.fill) { fillModal(dlg, JSON.parse(open.dataset.fill)); }
        dlg.showModal();
      }
      return;
    }
    var close = ev.target.closest('[data-modal-close]');
    if (close) { close.closest('dialog').close(); }
  });

  function fillModal(dlg, data) {
    Object.keys(data).forEach(function (key) {
      var input = dlg.querySelector('[name="' + key + '"]');
      if (!input) return;
      if (input.type === 'checkbox') { input.checked = !!Number(data[key]); }
      else { input.value = data[key] === null ? '' : data[key]; }
    });
    // checkboxes múltiplos (ex.: serviços do barbeiro)
    dlg.querySelectorAll('input[type="checkbox"][data-multi]').forEach(function (cb) {
      var list = data[cb.dataset.multi];
      cb.checked = Array.isArray(list) && list.indexOf(Number(cb.value)) !== -1;
    });
    var title = dlg.querySelector('[data-modal-title]');
    if (title && data._title) { title.textContent = data._title; }
  }

  // Confirmação em ações destrutivas
  document.addEventListener('submit', function (ev) {
    var msg = ev.target.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) { ev.preventDefault(); }
  });

  // Tabelas: copia o título da coluna para cada célula (usado no layout de cartões do celular)
  document.querySelectorAll('table.data').forEach(function (table) {
    var heads = Array.prototype.map.call(table.querySelectorAll('thead th'), function (th) {
      return th.textContent.trim();
    });
    table.querySelectorAll('tbody tr').forEach(function (tr) {
      Array.prototype.forEach.call(tr.children, function (td, i) {
        td.setAttribute('data-label', heads[i] || '');
        if (heads[i] && !td.querySelector('form, button')) {
          var wrap = document.createElement('span');
          wrap.className = 'td-v';
          while (td.firstChild) { wrap.appendChild(td.firstChild); }
          td.appendChild(wrap);
        }
      });
    });
  });

  // Envio automático de filtros
  document.querySelectorAll('[data-autosubmit]').forEach(function (el) {
    el.addEventListener('change', function () { el.form.submit(); });
  });
})();
</script>
</body>
</html>
