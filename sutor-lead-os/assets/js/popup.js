(function () {
  'use strict';
  var cfg = window.SutorLeadOS || {};
  var root = document.getElementById('sutor-lead-os-root');
  if (!root) return;

  var ATTR_KEYS = ['gclid', 'gbraid', 'wbraid', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
  var submitting = false;
  var lastLeadId = null;

  function qs(name) {
    try {
      return new URLSearchParams(window.location.search).get(name) || '';
    } catch (e) { return ''; }
  }

  function readAttribution() {
    var out = {};
    ATTR_KEYS.forEach(function (k) {
      var v = qs(k);
      if (v) {
        out[k] = v;
        try { sessionStorage.setItem('slos_' + k, v); } catch (e) {}
      } else {
        try { out[k] = sessionStorage.getItem('slos_' + k) || ''; } catch (e) { out[k] = ''; }
      }
    });
    out.landing_page = window.location.href;
    return out;
  }

  function pushDL(payload) {
    if (!cfg.enable_gtm) return;
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push(payload);
  }

  function el(tag, attrs, kids) {
    var n = document.createElement(tag);
    attrs = attrs || {};
    Object.keys(attrs).forEach(function (k) {
      if (k === 'className') n.className = attrs[k];
      else if (k === 'text') n.textContent = attrs[k];
      else if (k === 'html') n.innerHTML = attrs[k];
      else if (k.indexOf('on') === 0) n.addEventListener(k.slice(2).toLowerCase(), attrs[k]);
      else if (attrs[k] !== false && attrs[k] != null) n.setAttribute(k, attrs[k] === true ? '' : String(attrs[k]));
    });
    (kids || []).forEach(function (c) {
      if (c == null) return;
      n.appendChild(typeof c === 'string' ? document.createTextNode(c) : c);
    });
    return n;
  }

  function openModal() {
    root.querySelector('.slos-overlay').classList.add('is-open');
    root.querySelector('.slos-modal').classList.add('is-open');
    document.body.classList.add('slos-lock');
  }

  function closeModal() {
    root.querySelector('.slos-overlay').classList.remove('is-open');
    root.querySelector('.slos-modal').classList.remove('is-open');
    document.body.classList.remove('slos-lock');
  }

  function collectAnswers() {
    var answers = {};
    (cfg.questions || []).forEach(function (q) {
      if (q.type === 'multi') {
        var boxes = root.querySelectorAll('input[name="slos_' + q.id + '"]:checked');
        answers[q.id] = Array.prototype.map.call(boxes, function (b) { return b.value; });
      } else if (q.type === 'single') {
        var checked = root.querySelector('input[name="slos_' + q.id + '"]:checked');
        answers[q.id] = checked ? checked.value : '';
      } else {
        var input = root.querySelector('[name="slos_' + q.id + '"]');
        answers[q.id] = input ? input.value.trim() : '';
      }
    });
    return answers;
  }

  function validate(answers) {
    var err = [];
    (cfg.questions || []).forEach(function (q) {
      if (!q.required) return;
      var v = answers[q.id];
      if (v == null || v === '' || (Array.isArray(v) && !v.length)) {
        err.push(q.label || q.id);
      }
    });
    return err;
  }

  function setStatus(msg, isError) {
    var s = root.querySelector('.slos-status');
    if (!s) return;
    s.textContent = msg || '';
    s.className = 'slos-status' + (isError ? ' is-error' : (msg ? ' is-ok' : ''));
  }

  function submit() {
    if (submitting) return;
    var answers = collectAnswers();
    var missing = validate(answers);
    if (missing.length) {
      setStatus('請填寫：' + missing.join('、'), true);
      return;
    }
    submitting = true;
    setStatus('儲存中…');
    var btn = root.querySelector('.slos-cta');
    if (btn) btn.disabled = true;

    var body = Object.assign({ answers: answers }, readAttribution());

    fetch(cfg.rest_url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': cfg.nonce || ''
      },
      body: JSON.stringify(body)
    })
      .then(function (r) {
        return r.json().then(function (data) {
          return { ok: r.ok, status: r.status, data: data };
        });
      })
      .then(function (res) {
        if (!res.ok || !res.data || !res.data.lead_id) {
          var msg = (res.data && res.data.message) ? res.data.message : '儲存失敗，請稍後再試';
          setStatus(msg, true);
          submitting = false;
          if (btn) btn.disabled = false;
          return;
        }

        var d = res.data;
        lastLeadId = d.lead_id;

        // Fire conversion events only when a NEW lead was created.
        if (d.fire_events) {
          var base = {
            client_id: d.client_id,
            form_id: d.form_id,
            lead_id: d.lead_id,
            qualified: !!d.qualified,
            qualify_reason: d.qualify_reason || ''
          };
          pushDL(Object.assign({ event: 'lead_saved' }, base));
          if (d.qualified) {
            pushDL(Object.assign({ event: 'lead_qualified' }, base));
          }
        }

        // Open WA only AFTER lead_id returned.
        var wa = d.whatsapp_url;
        if (wa) {
          pushDL({
            event: 'whatsapp_open',
            client_id: d.client_id,
            form_id: d.form_id,
            lead_id: d.lead_id
          });
          try {
            fetch(cfg.rest_url.replace(/\/leads\/?$/, '/leads/' + encodeURIComponent(d.lead_id) + '/wa-opened'), {
              method: 'POST',
              credentials: 'same-origin',
              headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce || '' },
              body: '{}'
            });
          } catch (e) {}
          window.open(wa, '_blank', 'noopener');
        }
        setStatus('已儲存。Lead ID: ' + d.lead_id);
        submitting = false;
        if (btn) btn.disabled = false;
      })
      .catch(function () {
        setStatus('網絡錯誤，未有儲存，亦未開啟 WhatsApp。', true);
        submitting = false;
        if (btn) btn.disabled = false;
      });
  }

  function renderQuestion(q) {
    var block = el('fieldset', { className: 'slos-field', 'data-qid': q.id });
    var legend = el('legend', { text: q.label + (q.required ? ' *' : '') });
    block.appendChild(legend);
    if (q.type === 'single' || q.type === 'multi') {
      var list = el('div', { className: 'slos-options' });
      (q.options || []).forEach(function (opt) {
        var id = 'slos_' + q.id + '_' + opt.id;
        var lab = el('label', { className: 'slos-option', for: id });
        var input = el('input', {
          type: q.type === 'multi' ? 'checkbox' : 'radio',
          name: 'slos_' + q.id,
          id: id,
          value: opt.id
        });
        lab.appendChild(input);
        lab.appendChild(el('span', { text: opt.label }));
        list.appendChild(lab);
      });
      block.appendChild(list);
    } else {
      var input = el('input', {
        type: q.type === 'phone' ? 'tel' : 'text',
        name: 'slos_' + q.id,
        className: 'slos-text',
        autocomplete: q.type === 'phone' ? 'tel' : 'name',
        placeholder: q.type === 'phone' ? '+852…' : ''
      });
      block.appendChild(input);
    }
    return block;
  }

  function mount() {
    var fab = el('button', {
      type: 'button',
      className: 'slos-fab',
      'aria-label': cfg.fab_label || 'Open form',
      onClick: openModal
    }, [
      el('span', { className: 'slos-fab-icon', html: '&#128172;' }),
      el('span', { className: 'slos-fab-text', text: cfg.fab_label || '免費網站增長檢查' })
    ]);

    var overlay = el('div', { className: 'slos-overlay', onClick: closeModal });
    var modal = el('div', { className: 'slos-modal', role: 'dialog', 'aria-modal': 'true' });
    var header = el('div', { className: 'slos-header' }, [
      el('div', {}, [
        el('h2', { className: 'slos-title', text: cfg.popup_title || '' }),
        el('p', { className: 'slos-subtitle', text: cfg.popup_subtitle || '' })
      ]),
      el('button', { type: 'button', className: 'slos-close', 'aria-label': cfg.close_label || 'Close', text: '×', onClick: closeModal })
    ]);
    var form = el('div', { className: 'slos-form' });
    (cfg.questions || []).forEach(function (q) { form.appendChild(renderQuestion(q)); });
    var body = el('div', { className: 'slos-body' });
    body.appendChild(form);
    var footer = el('div', { className: 'slos-footer' }, [
      el('p', { className: 'slos-status' }),
      el('button', { type: 'button', className: 'slos-cta', onClick: submit }, [
        el('span', { className: 'slos-cta-icon', html: '&#128242;' }),
        el('span', { text: cfg.cta_label || '傳送資料到 WhatsApp' })
      ])
    ]);
    modal.appendChild(header);
    modal.appendChild(body);
    modal.appendChild(footer);
    root.appendChild(fab);
    root.appendChild(overlay);
    root.appendChild(modal);

    if (document.querySelector('[data-slos-open]')) {
      openModal();
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mount);
  } else {
    mount();
  }
})();
