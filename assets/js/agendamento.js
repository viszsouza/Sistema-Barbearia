/* =====================================================================
   Fluxo de agendamento em etapas.
   Toda disponibilidade vem do backend — o navegador só desenha.
   ===================================================================== */
(function () {
  'use strict';

  var DATA = JSON.parse(document.getElementById('booking-data').textContent);

  var state = {
    step: 1,
    service: null,
    barberId: null,      // null = qualquer barbeiro disponível
    barberName: null,
    date: null,
    time: null,
    assignedBarberId: null,
    assignedBarberName: null,
    customer: { name: '', phone: '', email: '', notes: '' },
    calendarMonth: new Date(DATA.today + 'T12:00:00')
  };

  var pendingBarber = null;

  var DOW = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
  var MONTHS = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
                'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];

  var $  = function (sel, ctx) { return (ctx || document).querySelector(sel); };
  var $$ = function (sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); };

  // ------------------------------------------------------------ utilidades
  function iso(d) {
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  }

  function prettyDate(isoStr) {
    var parts = isoStr.split('-');
    var d = new Date(+parts[0], +parts[1] - 1, +parts[2]);
    return DOW[d.getDay()] + ', ' + parts[2] + '/' + parts[1] + '/' + parts[0];
  }

  function feedback(message, type) {
    var box = $('#feedback');
    if (!message) { box.innerHTML = ''; return; }
    box.innerHTML = '<div class="alert alert--' + (type || 'info') + '">' + message + '</div>';
  }

  function maskPhone(value) {
    var d = value.replace(/\D/g, '').slice(0, 11);
    if (d.length <= 2) return d;
    if (d.length <= 6) return '(' + d.slice(0, 2) + ') ' + d.slice(2);
    if (d.length <= 10) return '(' + d.slice(0, 2) + ') ' + d.slice(2, 6) + '-' + d.slice(6);
    return '(' + d.slice(0, 2) + ') ' + d.slice(2, 7) + '-' + d.slice(7);
  }

  function api(path, options) {
    return fetch(DATA.apiBase + path, options).then(function (res) {
      return res.json().catch(function () { return { ok: false, error: 'Resposta inválida do servidor.' }; })
        .then(function (json) { return { status: res.status, body: json }; });
    });
  }

  // ---------------------------------------------------------------- etapas
  function goTo(step) {
    state.step = step;
    $$('.step-panel').forEach(function (panel) {
      panel.hidden = Number(panel.dataset.panel) !== step;
    });
    $$('#steps li').forEach(function (li) {
      var n = Number(li.dataset.step);
      li.dataset.state = n === step ? 'active' : (n < step ? 'done' : '');
    });
    feedback('');
    if (step > 1) {
      document.getElementById('steps').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  }

  function updateSummary() {
    var map = {
      service:  state.service ? state.service.name : '—',
      barber:   state.assignedBarberName || state.barberName || (state.barberId === null && state.step > 2 ? 'Qualquer disponível' : '—'),
      date:     state.date ? prettyDate(state.date) : '—',
      time:     state.time || '—',
      duration: state.service ? state.service.durationText : '—',
      price:    state.service ? state.service.priceText : '—'
    };
    Object.keys(map).forEach(function (key) {
      var el = $('[data-summary="' + key + '"]');
      if (el) el.textContent = map[key];
    });
  }

  // ------------------------------------------------------- ETAPA 1 serviço
  function renderServices() {
    var html = DATA.services.map(function (s) {
      return '<button type="button" class="choice" data-service="' + s.id + '" aria-pressed="false">' +
        '<span class="choice__title">' + s.name + '</span>' +
        '<span class="choice__meta">' + s.durationText + '</span>' +
        '<span class="choice__price">' + s.priceText + '</span>' +
        '</button>';
    }).join('');
    $('#service-list').innerHTML = html || '<div class="empty"><strong>Nenhum serviço disponível</strong>Volte mais tarde.</div>';
  }

  function selectService(id) {
    state.service = DATA.services.filter(function (s) { return s.id === id; })[0] || null;
    state.barberId = null; state.barberName = null;
    state.date = null; state.time = null;
    state.assignedBarberId = null; state.assignedBarberName = null;

    $$('#service-list .choice').forEach(function (btn) {
      btn.setAttribute('aria-pressed', String(Number(btn.dataset.service) === id));
    });
    updateSummary();
    renderBarbers();
    goTo(2);

    if (pendingBarber && (DATA.serviceBarbers[id] || []).indexOf(pendingBarber) !== -1) {
      var wanted = pendingBarber;
      pendingBarber = null;
      selectBarber(String(wanted));
    }
  }

  // ------------------------------------------------------ ETAPA 2 barbeiro
  function renderBarbers() {
    if (!state.service) return;
    var allowed = DATA.serviceBarbers[state.service.id] || [];
    var list = DATA.barbers.filter(function (b) { return allowed.indexOf(b.id) !== -1; });

    var html = '<button type="button" class="choice" data-barber="any" aria-pressed="false">' +
      '<span class="choice__title">Qualquer barbeiro disponível</span>' +
      '<span class="choice__meta">Mostra todos os horários livres da casa</span></button>';

    html += list.map(function (b) {
      return '<button type="button" class="choice" data-barber="' + b.id + '" aria-pressed="false">' +
        '<span class="choice__title">' + b.name + '</span>' +
        '<span class="choice__meta">' + (b.headline || '') + '</span></button>';
    }).join('');

    $('#barber-list').innerHTML = list.length ? html :
      '<div class="empty"><strong>Nenhum barbeiro atende esse serviço</strong>Escolha outro serviço para continuar.</div>';
  }

  function selectBarber(value) {
    state.barberId = value === 'any' ? null : Number(value);
    var found = DATA.barbers.filter(function (b) { return b.id === state.barberId; })[0];
    state.barberName = found ? found.name : 'Qualquer disponível';
    state.date = null; state.time = null;
    state.assignedBarberId = null; state.assignedBarberName = null;

    $$('#barber-list .choice').forEach(function (btn) {
      btn.setAttribute('aria-pressed', String(btn.dataset.barber === String(value)));
    });
    updateSummary();
    state.calendarMonth = new Date(DATA.today + 'T12:00:00');
    loadCalendar();
    goTo(3);
  }

  // ---------------------------------------------------------- ETAPA 3 data
  function loadCalendar() {
    var y = state.calendarMonth.getFullYear();
    var m = state.calendarMonth.getMonth() + 1;
    var monthKey = y + '-' + String(m).padStart(2, '0');

    $('#calendar').innerHTML = '<div class="skeleton" style="height:300px"></div>';

    var q = '?service_id=' + state.service.id + '&month=' + monthKey +
            (state.barberId ? '&barber_id=' + state.barberId : '');

    api('/availability.php' + q).then(function (res) {
      if (!res.body.ok) { feedback(res.body.error || 'Não foi possível carregar o calendário.', 'error'); return; }
      renderCalendar(y, m, res.body.days || []);
    }).catch(function () {
      feedback('Falha de conexão ao carregar o calendário.', 'error');
    });
  }

  function renderCalendar(year, month, openDays) {
    var first = new Date(year, month - 1, 1);
    var daysInMonth = new Date(year, month, 0).getDate();
    var offset = first.getDay();

    var prevDisabled = (year + '-' + String(month).padStart(2, '0')) <= DATA.today.slice(0, 7);
    var nextDisabled = (year + '-' + String(month).padStart(2, '0')) >= DATA.maxDate.slice(0, 7);

    var html = '<div class="calendar__head">' +
      '<button class="calendar__nav" type="button" data-month="-1"' + (prevDisabled ? ' disabled' : '') + ' aria-label="Mês anterior">‹</button>' +
      '<span class="calendar__title">' + MONTHS[month - 1] + ' ' + year + '</span>' +
      '<button class="calendar__nav" type="button" data-month="1"' + (nextDisabled ? ' disabled' : '') + ' aria-label="Próximo mês">›</button>' +
      '</div><div class="calendar__grid">';

    html += DOW.map(function (d) { return '<div class="calendar__dow">' + d + '</div>'; }).join('');
    for (var i = 0; i < offset; i++) { html += '<div></div>'; }

    for (var day = 1; day <= daysInMonth; day++) {
      var key = year + '-' + String(month).padStart(2, '0') + '-' + String(day).padStart(2, '0');
      var open = openDays.indexOf(key) !== -1;
      html += '<button type="button" class="calendar__day" data-date="' + key + '"' +
        (open ? '' : ' disabled') +
        (key === DATA.today ? ' data-today="true"' : '') +
        ' aria-pressed="' + (state.date === key) + '">' + day + '</button>';
    }

    html += '</div>';
    if (!openDays.length) {
      html += '<p class="dim" style="margin:14px 0 0;font-size:.88rem">Sem vagas neste mês. Tente o mês seguinte.</p>';
    }
    $('#calendar').innerHTML = html;
  }

  function selectDate(dateKey) {
    state.date = dateKey;
    state.time = null;
    state.assignedBarberId = null;
    state.assignedBarberName = null;
    updateSummary();
    loadSlots();
    goTo(4);
  }

  // ------------------------------------------------------- ETAPA 4 horário
  function loadSlots() {
    $('#slot-context').textContent = prettyDate(state.date) + ' · ' + state.service.name + ' · ' + state.service.durationText;
    var placeholders = '';
    for (var i = 0; i < 8; i++) { placeholders += '<div class="skeleton skeleton--slot"></div>'; }
    $('#slot-list').innerHTML = placeholders;

    var q = '?service_id=' + state.service.id + '&date=' + state.date +
            (state.barberId ? '&barber_id=' + state.barberId : '');

    api('/availability.php' + q).then(function (res) {
      if (!res.body.ok) {
        $('#slot-list').innerHTML = '';
        feedback(res.body.error || 'Não foi possível carregar os horários.', 'error');
        return;
      }
      var slots = res.body.slots || [];
      if (!slots.length) {
        $('#slot-list').innerHTML = '<div class="empty" style="grid-column:1/-1"><strong>Nenhum horário livre</strong>' +
          (res.body.message || 'Escolha outra data.') + '</div>';
        return;
      }
      $('#slot-list').innerHTML = slots.map(function (slot) {
        return '<button type="button" class="slot" data-time="' + slot.time + '" data-barber="' + slot.barber_id +
          '" data-barber-name="' + slot.barber_name + '" aria-pressed="false">' + slot.time + '</button>';
      }).join('');
    }).catch(function () {
      $('#slot-list').innerHTML = '';
      feedback('Falha de conexão ao carregar os horários.', 'error');
    });
  }

  function selectSlot(btn) {
    state.time = btn.dataset.time;
    state.assignedBarberId = Number(btn.dataset.barber);
    state.assignedBarberName = btn.dataset.barberName;

    $$('#slot-list .slot').forEach(function (s) {
      s.setAttribute('aria-pressed', String(s === btn));
    });
    updateSummary();

    if (DATA.customer) {
      $('#f-name').value  = DATA.customer.name || '';
      $('#f-phone').value = DATA.customer.phone || '';
      $('#f-email').value = DATA.customer.email || '';
    }
    goTo(5);
  }

  // -------------------------------------------------------- ETAPA 5 dados
  function clearFieldErrors() {
    $$('#customer-form .field').forEach(function (f) {
      f.dataset.error = 'false';
      $('.field__error', f).textContent = '';
    });
  }

  function showFieldErrors(fields) {
    Object.keys(fields || {}).forEach(function (key) {
      var f = $('#customer-form [data-field="' + key + '"]');
      if (!f) return;
      f.dataset.error = 'true';
      $('.field__error', f).textContent = fields[key];
    });
  }

  function validateCustomer() {
    clearFieldErrors();
    var errors = {};
    var name  = $('#f-name').value.trim();
    var phone = $('#f-phone').value.replace(/\D/g, '');
    var email = $('#f-email').value.trim();

    if (name.length < 3) errors.name = 'Informe seu nome completo.';
    if (phone.length < 10 || phone.length > 11) errors.phone = 'Informe o telefone com DDD.';
    if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) errors.email = 'E-mail inválido.';

    if (Object.keys(errors).length) { showFieldErrors(errors); return false; }

    state.customer = { name: name, phone: $('#f-phone').value.trim(), email: email, notes: $('#f-notes').value.trim() };
    return true;
  }

  // -------------------------------------------------- ETAPA 6 confirmação
  function renderReview() {
    var rows = [
      ['Serviço',  state.service.name],
      ['Barbeiro', state.assignedBarberName],
      ['Data',     prettyDate(state.date)],
      ['Horário',  state.time],
      ['Duração',  state.service.durationText],
      ['Valor',    state.service.priceText],
      ['Cliente',  state.customer.name],
      ['Telefone', state.customer.phone]
    ];
    if (state.customer.email) rows.push(['E-mail', state.customer.email]);
    if (state.customer.notes) rows.push(['Observação', state.customer.notes]);

    $('#review').innerHTML = rows.map(function (r) {
      return '<div class="schedule-line"><span>' + r[0] + '</span><span style="color:var(--text)">' + r[1] + '</span></div>';
    }).join('');
  }

  function confirmBooking() {
    var btn = $('#confirm-btn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Reservando…';

    api('/appointments.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': DATA.csrf },
      body: JSON.stringify({
        action: 'create',
        csrf_token: DATA.csrf,
        service_id: state.service.id,
        barber_id: state.assignedBarberId,
        date: state.date,
        time: state.time,
        name: state.customer.name,
        phone: state.customer.phone,
        email: state.customer.email,
        notes: state.customer.notes
      })
    }).then(function (res) {
      if (res.body.ok) {
        window.location.href = res.body.redirect;
        return;
      }
      btn.disabled = false;
      btn.textContent = 'Confirmar agendamento';

      if (res.status === 409) {
        feedback(res.body.error + ' Escolhendo outro horário…', 'error');
        loadSlots();
        goTo(4);
        return;
      }
      if (res.body.fields) { showFieldErrors(res.body.fields); goTo(5); }
      feedback(res.body.error || 'Não foi possível concluir o agendamento.', 'error');
    }).catch(function () {
      btn.disabled = false;
      btn.textContent = 'Confirmar agendamento';
      feedback('Falha de conexão. Verifique sua internet e tente de novo.', 'error');
    });
  }

  // ------------------------------------------------------------- eventos
  document.addEventListener('click', function (ev) {
    var t = ev.target.closest('[data-service],[data-barber],[data-date],[data-time],[data-back],[data-month]');
    if (!t) return;

    if (t.hasAttribute('data-service')) { selectService(Number(t.dataset.service)); }
    else if (t.hasAttribute('data-barber') && t.classList.contains('choice')) { selectBarber(t.dataset.barber); }
    else if (t.hasAttribute('data-date') && !t.disabled) { selectDate(t.dataset.date); }
    else if (t.classList.contains('slot') && !t.disabled) { selectSlot(t); }
    else if (t.hasAttribute('data-back')) { goTo(Number(t.dataset.back)); }
    else if (t.hasAttribute('data-month') && !t.disabled) {
      state.calendarMonth.setMonth(state.calendarMonth.getMonth() + Number(t.dataset.month));
      loadCalendar();
    }
  });

  $('#customer-form').addEventListener('submit', function (ev) {
    ev.preventDefault();
    if (!validateCustomer()) return;
    renderReview();
    goTo(6);
  });

  $('#f-phone').addEventListener('input', function () {
    this.value = maskPhone(this.value);
  });

  $('#confirm-btn').addEventListener('click', confirmBooking);

  // ------------------------------------------------------------ inicializa
  renderServices();
  updateSummary();

  if (DATA.preselect.service) {
    selectService(DATA.preselect.service);
    if (DATA.preselect.barber && (DATA.serviceBarbers[DATA.preselect.service] || []).indexOf(DATA.preselect.barber) !== -1) {
      selectBarber(String(DATA.preselect.barber));
    }
  } else if (DATA.preselect.barber) {
    pendingBarber = DATA.preselect.barber;
    var chosen = DATA.barbers.filter(function (b) { return b.id === pendingBarber; })[0];
    if (chosen) {
      feedback('Escolha o serviço e já deixamos ' + chosen.name + ' selecionado para você.', 'info');
    }
  }
})();
