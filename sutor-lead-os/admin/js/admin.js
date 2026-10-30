(function () {
  'use strict';
  var cfg = window.SutorLeadOSAdmin || {};
  var state = JSON.parse(JSON.stringify(cfg.settings || {}));
  if (!Array.isArray(state.questions)) state.questions = [];
  if (!Array.isArray(state.qualification_rules)) state.qualification_rules = [];

  function uid(prefix) {
    return prefix + '_' + Math.random().toString(36).slice(2, 9);
  }

  function el(tag, attrs, children) {
    var node = document.createElement(tag);
    attrs = attrs || {};
    Object.keys(attrs).forEach(function (k) {
      if (k === 'className') node.className = attrs[k];
      else if (k === 'text') node.textContent = attrs[k];
      else if (k === 'html') node.innerHTML = attrs[k];
      else if (k.indexOf('on') === 0) node.addEventListener(k.slice(2).toLowerCase(), attrs[k]);
      else if (attrs[k] !== false && attrs[k] != null) node.setAttribute(k, attrs[k]);
    });
    (children || []).forEach(function (c) {
      if (c == null) return;
      node.appendChild(typeof c === 'string' ? document.createTextNode(c) : c);
    });
    return node;
  }

  function collectBasics() {
    var en = document.getElementById('slos-enabled');
    state.enabled = en && en.checked ? 1 : 0;
    state.popup_title = document.getElementById('slos-popup-title').value;
    state.popup_subtitle = document.getElementById('slos-popup-subtitle').value;
    state.fab_label = document.getElementById('slos-fab-label').value;
    state.cta_label = document.getElementById('slos-cta-label').value;
    state.close_label = document.getElementById('slos-close-label').value;
    state.auto_show_fab = document.getElementById('slos-auto-fab').checked ? 1 : 0;
    state.client_id = document.getElementById('slos-client-id').value;
    state.form_id = document.getElementById('slos-form-id').value;
    state.whatsapp_number = document.getElementById('slos-wa-number').value;
    state.whatsapp_template = document.getElementById('slos-wa-tpl').value;
    state.enable_gtm = document.getElementById('slos-enable-gtm').checked ? 1 : 0;
    state.supabase_url = document.getElementById('slos-sb-url').value;
    state.supabase_anon_key = document.getElementById('slos-sb-key').value;
  }

  function renderQuestions() {
    var root = document.getElementById('slos-questions');
    root.innerHTML = '';
    state.questions.forEach(function (q, qi) {
      var card = el('div', { className: 'slos-q-card', 'data-qi': String(qi) });
      var head = el('div', { className: 'slos-q-head' }, [
        el('strong', { text: 'Question ' + (qi + 1) }),
        el('div', { className: 'slos-q-actions' }, [
          el('button', { type: 'button', className: 'button', text: 'Up', onClick: function () { moveQ(qi, -1); } }),
          el('button', { type: 'button', className: 'button', text: 'Down', onClick: function () { moveQ(qi, 1); } }),
          el('button', { type: 'button', className: 'button button-link-delete', text: 'Remove', onClick: function () {
            state.questions.splice(qi, 1); renderQuestions(); renderRules();
          } })
        ])
      ]);
      card.appendChild(head);

      card.appendChild(labeledInput('Label', 'text', q.label, function (v) { q.label = v; }));
      card.appendChild(labeledInput('Field ID', 'text', q.id, function (v) { q.id = v; renderRules(); }));

      var typeWrap = el('label', { className: 'slos-field', text: 'Type ' });
      var typeSel = el('select');
      [['single', 'Single choice'], ['multi', 'Multi choice'], ['text', 'Text'], ['phone', 'Phone']].forEach(function (opt) {
        var o = el('option', { value: opt[0], text: opt[1] });
        if (q.type === opt[0]) o.selected = true;
        typeSel.appendChild(o);
      });
      typeSel.addEventListener('change', function () {
        q.type = typeSel.value;
        if ((q.type === 'single' || q.type === 'multi') && (!q.options || !q.options.length)) {
          q.options = [{ id: uid('o'), label: 'Option 1' }];
        }
        renderQuestions();
      });
      typeWrap.appendChild(typeSel);
      card.appendChild(typeWrap);

      var req = el('label', { className: 'slos-check' }, [
        el('input', { type: 'checkbox' }),
        document.createTextNode(' Required')
      ]);
      req.querySelector('input').checked = !!q.required;
      req.querySelector('input').addEventListener('change', function (e) { q.required = e.target.checked ? 1 : 0; });
      card.appendChild(req);

      if (q.type === 'single' || q.type === 'multi') {
        if (!Array.isArray(q.options)) q.options = [];
        var optBox = el('div', { className: 'slos-options' });
        optBox.appendChild(el('h4', { text: 'Answer options' }));
        q.options.forEach(function (opt, oi) {
          var row = el('div', { className: 'slos-opt-row' }, [
            labeledInput('Option ID', 'text', opt.id, function (v) { opt.id = v; renderRules(); }, true),
            labeledInput('Label', 'text', opt.label, function (v) { opt.label = v; }, true),
            el('button', { type: 'button', className: 'button', text: '↑', onClick: function () { moveOpt(q, oi, -1); } }),
            el('button', { type: 'button', className: 'button', text: '↓', onClick: function () { moveOpt(q, oi, 1); } }),
            el('button', { type: 'button', className: 'button', text: '×', onClick: function () {
              q.options.splice(oi, 1); renderQuestions(); renderRules();
            } })
          ]);
          optBox.appendChild(row);
        });
        optBox.appendChild(el('button', { type: 'button', className: 'button', text: 'Add option', onClick: function () {
          q.options.push({ id: uid('o'), label: 'New option' });
          renderQuestions();
        } }));
        card.appendChild(optBox);
      }
      root.appendChild(card);
    });
  }

  function labeledInput(label, type, value, onChange, inline) {
    var wrap = el('label', { className: inline ? 'slos-field slos-inline' : 'slos-field' });
    wrap.appendChild(document.createTextNode(label + ' '));
    var input = el('input', { type: type, value: value || '' });
    input.addEventListener('input', function () { onChange(input.value); });
    wrap.appendChild(input);
    return wrap;
  }

  function moveQ(i, dir) {
    var j = i + dir;
    if (j < 0 || j >= state.questions.length) return;
    var tmp = state.questions[i];
    state.questions[i] = state.questions[j];
    state.questions[j] = tmp;
    renderQuestions();
  }

  function moveOpt(q, i, dir) {
    var j = i + dir;
    if (j < 0 || j >= q.options.length) return;
    var tmp = q.options[i];
    q.options[i] = q.options[j];
    q.options[j] = tmp;
    renderQuestions();
  }

  function renderRules() {
    var root = document.getElementById('slos-rules');
    root.innerHTML = '';
    state.qualification_rules.forEach(function (rule, ri) {
      var card = el('div', { className: 'slos-rule-card' });
      card.appendChild(el('div', { className: 'slos-q-head' }, [
        el('strong', { text: 'Rule ' + (ri + 1) }),
        el('button', { type: 'button', className: 'button button-link-delete', text: 'Remove', onClick: function () {
          state.qualification_rules.splice(ri, 1); renderRules();
        } })
      ]));

      var qSel = el('select');
      state.questions.forEach(function (q) {
        var o = el('option', { value: q.id, text: q.label + ' (' + q.id + ')' });
        if (rule.question_id === q.id) o.selected = true;
        qSel.appendChild(o);
      });
      qSel.addEventListener('change', function () { rule.question_id = qSel.value; renderRules(); });
      card.appendChild(el('label', { className: 'slos-field' }, [document.createTextNode('Question '), qSel]));

      var opSel = el('select');
      [['in', 'Answer is one of'], ['eq', 'Answer equals'], ['intersects', 'Answers intersect (multi)']].forEach(function (opt) {
        var o = el('option', { value: opt[0], text: opt[1] });
        if (rule.operator === opt[0]) o.selected = true;
        opSel.appendChild(o);
      });
      opSel.addEventListener('change', function () { rule.operator = opSel.value; });
      card.appendChild(el('label', { className: 'slos-field' }, [document.createTextNode('Operator '), opSel]));

      var q = state.questions.find(function (x) { return x.id === rule.question_id; });
      var ansBox = el('div', { className: 'slos-rule-answers' });
      ansBox.appendChild(el('div', { text: 'Matching answer IDs:' }));
      if (q && (q.type === 'single' || q.type === 'multi') && q.options) {
        if (!Array.isArray(rule.answer_ids)) rule.answer_ids = [];
        q.options.forEach(function (opt) {
          var lab = el('label', { className: 'slos-check' });
          var cb = el('input', { type: 'checkbox' });
          cb.checked = rule.answer_ids.indexOf(opt.id) !== -1;
          cb.addEventListener('change', function () {
            if (cb.checked) {
              if (rule.answer_ids.indexOf(opt.id) === -1) rule.answer_ids.push(opt.id);
            } else {
              rule.answer_ids = rule.answer_ids.filter(function (id) { return id !== opt.id; });
            }
          });
          lab.appendChild(cb);
          lab.appendChild(document.createTextNode(' ' + opt.label + ' (' + opt.id + ')'));
          ansBox.appendChild(lab);
        });
      } else {
        ansBox.appendChild(el('p', { className: 'description', text: 'Pick a single/multi question to choose answer IDs.' }));
      }
      card.appendChild(ansBox);
      card.appendChild(labeledInput('Reason code', 'text', rule.reason || '', function (v) { rule.reason = v; }));
      card.appendChild(labeledInput('Reason label', 'text', rule.reason_label || '', function (v) { rule.reason_label = v; }));
      root.appendChild(card);
    });
  }

  function payload() {
    collectBasics();
    return state;
  }

  function syncHidden() {
    document.getElementById('slos-settings-json').value = JSON.stringify(payload());
  }

  document.getElementById('slos-add-question').addEventListener('click', function () {
    state.questions.push({
      id: uid('q'),
      label: 'New question',
      type: 'single',
      required: 1,
      options: [{ id: uid('o'), label: 'Option A' }, { id: uid('o'), label: 'Option B' }]
    });
    renderQuestions();
  });

  document.getElementById('slos-add-rule').addEventListener('click', function () {
    var qid = state.questions[0] ? state.questions[0].id : '';
    state.qualification_rules.push({
      id: uid('rule'),
      question_id: qid,
      operator: 'in',
      answer_ids: [],
      reason: 'qualified',
      reason_label: 'Qualified'
    });
    renderRules();
  });

  document.getElementById('slos-settings-form').addEventListener('submit', function () {
    syncHidden();
  });

  document.getElementById('slos-ajax-save').addEventListener('click', function () {
    var body = new FormData();
    body.append('action', 'sutor_lead_os_save');
    body.append('nonce', cfg.nonce);
    body.append('settings', JSON.stringify(payload()));
    fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (res.success) {
          state = res.data.settings;
          alert('Saved.');
          renderQuestions();
          renderRules();
        } else {
          alert((res.data && res.data.message) || 'Save failed');
        }
      })
      .catch(function (err) { alert('Save failed: ' + err); });
  });


  function hydrateBasics() {
    var map = {
      'slos-popup-title': 'popup_title',
      'slos-popup-subtitle': 'popup_subtitle',
      'slos-fab-label': 'fab_label',
      'slos-cta-label': 'cta_label',
      'slos-close-label': 'close_label',
      'slos-client-id': 'client_id',
      'slos-form-id': 'form_id',
      'slos-wa-number': 'whatsapp_number',
      'slos-wa-tpl': 'whatsapp_template',
      'slos-sb-url': 'supabase_url',
      'slos-sb-key': 'supabase_anon_key'
    };
    Object.keys(map).forEach(function (id) {
      var eln = document.getElementById(id);
      if (eln) eln.value = state[map[id]] != null ? state[map[id]] : '';
    });
    var en = document.getElementById('slos-enabled');
    if (en) en.checked = state.enabled !== 0 && state.enabled !== false && state.enabled !== '0';
    var enLabel = document.getElementById('slos-enabled-label');
    if (enLabel) enLabel.textContent = en && en.checked ? 'On' : 'Off';
    var fab = document.getElementById('slos-auto-fab');
    if (fab) fab.checked = !!state.auto_show_fab;
    var gtm = document.getElementById('slos-enable-gtm');
    if (gtm) gtm.checked = !!state.enable_gtm;
  }


  var AI_PROMPT = [
    'You write WordPress Lead OS form configs as JSON only (no markdown fences).',
    'Output one JSON object matching this schema for the website I describe.',
    '',
    '{',
    '  "client_id": "slug-for-client",',
    '  "form_id": "main",',
    '  "popup_title": "…",',
    '  "popup_subtitle": "…",',
    '  "fab_label": "…",',
    '  "cta_label": "傳送資料到 WhatsApp",',
    '  "close_label": "關閉",',
    '  "auto_show_fab": 1,',
    '  "whatsapp_number": "852XXXXXXXX",',
    '  "whatsapp_template": "你好，我想查詢。\\nLead: {lead_id}\\n{answers}",',
    '  "enable_gtm": 1,',
    '  "questions": [',
    '    {',
    '      "id": "q1",',
    '      "label": "問題文字",',
    '      "type": "single",',
    '      "required": 1,',
    '      "options": [',
    '        { "id": "a", "label": "選項 A" },',
    '        { "id": "b", "label": "選項 B" }',
    '      ]',
    '    }',
    '  ],',
    '  "qualification_rules": [',
    '    {',
    '      "id": "rule1",',
    '      "question_id": "q1",',
    '      "operator": "in",',
    '      "answer_ids": ["a"],',
    '      "reason": "high_intent",',
    '      "reason_label": "High intent"',
    '    }',
    '  ]',
    '}',
    '',
    'Rules:',
    '- type must be one of: single, multi, text, phone',
    '- ids: lowercase letters, numbers, underscore only',
    '- qualification_rules.operator: eq | in | intersects',
    '- Do not include supabase keys unless I give them',
    '- Language of labels: match the site (usually zh-HK)',
    '',
    'Website brief:'
  ].join('\\n');

  var SCHEMA_HINT = AI_PROMPT;

  function setImportStatus(msg, ok) {
    var eln = document.getElementById('slos-import-status');
    if (!eln) return;
    eln.textContent = msg || '';
    eln.style.color = ok === false ? '#b32d2e' : (ok ? '#00a32a' : '#646970');
  }

  function normalizeImported(data) {
    if (!data || typeof data !== 'object' || Array.isArray(data)) {
      throw new Error('JSON must be an object');
    }
    // Allow wrapping { settings: {...} } or { config: {...} }
    if (data.settings && typeof data.settings === 'object') data = data.settings;
    if (data.config && typeof data.config === 'object') data = data.config;

    var next = JSON.parse(JSON.stringify(state));
    var keys = [
      'enabled','client_id','form_id','popup_title','popup_subtitle','fab_label','cta_label',
      'close_label','auto_show_fab','whatsapp_number','whatsapp_template','enable_gtm',
      'supabase_url','supabase_anon_key','questions','qualification_rules'
    ];
    keys.forEach(function (k) {
      if (data[k] !== undefined) next[k] = data[k];
    });
    if (!Array.isArray(next.questions)) throw new Error('questions must be an array');
    if (!Array.isArray(next.qualification_rules)) next.qualification_rules = [];

    next.questions = next.questions.map(function (q, i) {
      if (!q || typeof q !== 'object') throw new Error('Invalid question at index ' + i);
      var type = q.type || 'single';
      if (['single','multi','text','phone'].indexOf(type) < 0) {
        throw new Error('Invalid question type: ' + type);
      }
      return {
        id: String(q.id || uid('q')).replace(/[^a-z0-9_]/gi, '_').toLowerCase(),
        label: String(q.label || ''),
        type: type,
        required: q.required ? 1 : 0,
        options: Array.isArray(q.options) ? q.options.map(function (o) {
          return {
            id: String((o && o.id) || uid('o')).replace(/[^a-z0-9_]/gi, '_').toLowerCase(),
            label: String((o && o.label) || '')
          };
        }) : []
      };
    });
    next.qualification_rules = next.qualification_rules.map(function (r, i) {
      if (!r || typeof r !== 'object') throw new Error('Invalid rule at index ' + i);
      return {
        id: String(r.id || uid('rule')).replace(/[^a-z0-9_]/gi, '_').toLowerCase(),
        question_id: String(r.question_id || ''),
        operator: ['eq','in','intersects'].indexOf(r.operator) >= 0 ? r.operator : 'in',
        answer_ids: Array.isArray(r.answer_ids) ? r.answer_ids.map(String) : [],
        reason: String(r.reason || 'qualified'),
        reason_label: String(r.reason_label || '')
      };
    });
    if (next.enabled === undefined) next.enabled = 1;
    next.enabled = next.enabled ? 1 : 0;
    next.auto_show_fab = next.auto_show_fab ? 1 : 0;
    next.enable_gtm = next.enable_gtm ? 1 : 0;
    return next;
  }

  function applyImport(rawText) {
    var data;
    try {
      data = JSON.parse(rawText);
    } catch (e) {
      throw new Error('Invalid JSON: ' + e.message);
    }
    state = normalizeImported(data);
    hydrateBasics();
    renderQuestions();
    renderRules();
    syncHidden();
  }

  var importBtn = document.getElementById('slos-import-apply');
  if (importBtn) {
    importBtn.addEventListener('click', function () {
      var raw = (document.getElementById('slos-import-json').value || '').trim();
      if (!raw) { setImportStatus('Paste JSON first.', false); return; }
      try {
        applyImport(raw);
        setImportStatus('Imported into the form. Review, then click Save settings.', true);
      } catch (e) {
        setImportStatus(e.message || String(e), false);
      }
    });
  }

  var fileBtn = document.getElementById('slos-import-file-btn');
  var fileInput = document.getElementById('slos-import-file');
  if (fileBtn && fileInput) {
    fileBtn.addEventListener('click', function () { fileInput.click(); });
    fileInput.addEventListener('change', function () {
      var f = fileInput.files && fileInput.files[0];
      if (!f) return;
      var reader = new FileReader();
      reader.onload = function () {
        try {
          document.getElementById('slos-import-json').value = String(reader.result || '');
          applyImport(String(reader.result || ''));
          setImportStatus('Imported from ' + f.name + '. Review, then Save settings.', true);
        } catch (e) {
          setImportStatus(e.message || String(e), false);
        }
      };
      reader.readAsText(f);
    });
  }

  var exportBtn = document.getElementById('slos-export-json');
  if (exportBtn) {
    exportBtn.addEventListener('click', function () {
      collectBasics();
      var blob = new Blob([JSON.stringify(state, null, 2)], { type: 'application/json' });
      var a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = (state.client_id || 'lead-os') + '-config.json';
      a.click();
      URL.revokeObjectURL(a.href);
      setImportStatus('Exported current config.', true);
    });
  }

  var promptBtn = document.getElementById('slos-copy-ai-prompt');
  if (promptBtn) {
    promptBtn.addEventListener('click', function () {
      var text = AI_PROMPT + '\\n';
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function () {
          setImportStatus('AI prompt copied. Paste it into ChatGPT, add the website brief, then paste the JSON back here.', true);
        }).catch(function () {
          document.getElementById('slos-import-json').value = text;
          setImportStatus('Could not copy — prompt placed in the paste box.', true);
        });
      } else {
        document.getElementById('slos-import-json').value = text;
        setImportStatus('Prompt placed in the paste box.', true);
      }
    });
  }

  var hint = document.getElementById('slos-schema-hint');
  if (hint) hint.textContent = SCHEMA_HINT;



  var enabledInput = document.getElementById('slos-enabled');
  if (enabledInput) {
    enabledInput.addEventListener('change', function () {
      var label = document.getElementById('slos-enabled-label');
      if (label) label.textContent = enabledInput.checked ? 'On' : 'Off';
    });
  }

  hydrateBasics();
  renderQuestions();
  renderRules();
})();
