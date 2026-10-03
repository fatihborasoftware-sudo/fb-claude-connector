/* Watch Me Live v2 — preview-first. Plays connector events; your site stays clickable. */
(function () {
	'use strict';
	var C = window.FBCC_LIVE;
	var $ = function (id) { return document.getElementById(id); };
	var root = $('wl');
	if (!C || !root) { return; }

	var frame = $('wl-frame');
	var last = 0, queue = [], playing = false;
	var follow = true, ourNav = false, curUrl = '';
	var lastStamp = '', lastTaskKey = '', lastPendCount = -1, lastFinish = null;
	var lastBrowser = { time: 0, url: '', title: '' }, lastConnectorTs = 0, screenSeq = 0, screenOn = false;
	var snapshots = {};
	var toastUrl = '';
	var LEVELS = { read: 'Read-only', editor: 'Content editor', maintainer: 'Site maintainer' };

	$('wl-host').textContent = C.site || '';

	/* ---------- voice briefs (0.7.0) ---------- */
	var V = { on: false, read: { start: true, step: true, wait: true, finish: true, click: false }, voice: '', rate: 1, lang: 'en' };
	try { var sv = JSON.parse(window.localStorage.getItem('fbcc_voice2') || 'null'); if (sv) { V.on = !!sv.on; V.read = Object.assign(V.read, sv.read || {}); V.voice = sv.voice || ''; V.rate = +sv.rate || 1; } else { V.on = window.localStorage.getItem('fbcc_voice') === '1'; } } catch (e) {}
	function saveV() { try { window.localStorage.setItem('fbcc_voice2', JSON.stringify({ on: V.on, read: V.read, voice: V.voice, rate: V.rate })); } catch (e) {} }

	var T = {
		en: {
			on: 'Voice on', off: 'Voice off', hello: 'Voice on. I will read Claude’s briefs.',
			start: function (n, t, k, first) { return (n ? 'Hi ' + n + '. ' : '') + 'I am starting: ' + t + '.' + (k > 1 ? ' ' + k + ' steps.' : '') + (first ? ' First: ' + first + '.' : ''); },
			wait: function (x) { return 'Waiting for you: ' + x; },
			done: function (x) { return 'Done. ' + (x || ''); },
			page: function (x) { return 'Opening ' + x + '.'; },
			click: function (x) { return 'Clicked ' + x + '.'; },
			test: 'This is how Claude’s briefs will sound.'
		},
		tr: {
			on: 'Ses açık', off: 'Ses kapalı', hello: 'Ses açık. Claude’un özetlerini okuyacağım.',
			start: function (n, t, k, first) { return (n ? 'Merhaba ' + n + '. ' : '') + 'Başlıyorum: ' + t + '.' + (k > 1 ? ' ' + k + ' adım.' : '') + (first ? ' İlk olarak: ' + first + '.' : ''); },
			wait: function (x) { return 'Onayınızı bekliyorum: ' + x; },
			done: function (x) { return 'Bitti. ' + (x || ''); },
			page: function (x) { return x + ' açılıyor.'; },
			click: function (x) { return x + ' tıklandı.'; },
			test: 'Claude’un özetleri böyle duyulacak.'
		}
	};
	function tr() { return T[V.lang] || T.en; }

	var speakQ = [], speaking = false, lastLine = '';
	function pickVoice() {
		if (!window.speechSynthesis) { return null; }
		var all = window.speechSynthesis.getVoices() || [];
		if (V.voice) { for (var i = 0; i < all.length; i++) { if (all[i].voiceURI === V.voice) { return all[i]; } } }
		for (var j = 0; j < all.length; j++) { if ((all[j].lang || '').toLowerCase().indexOf(V.lang) === 0) { return all[j]; } }
		return null;
	}
	function showSpeak(text, live) {
		$('wl-speak').hidden = !V.on || !text;
		$('wl-speak-t').textContent = text ? '“' + text + '”' : '';
		$('wl-speak-k').textContent = live ? (V.lang === 'tr' ? 'ŞU AN OKUNUYOR' : 'SPEAKING NOW') : (V.lang === 'tr' ? 'SON ÖZET' : 'LAST BRIEF');
		$('wl-speak').classList.toggle('is-live', !!live);
	}
	function next() {
		if (speaking || !speakQ.length) { return; }
		var text = speakQ.shift();
		if (!V.on || !window.speechSynthesis) { speakQ = []; return; }
		speaking = true; lastLine = text; showSpeak(text, true);
		try {
			var u = new SpeechSynthesisUtterance(text);
			var v = pickVoice(); if (v) { u.voice = v; u.lang = v.lang; } else { u.lang = V.lang === 'tr' ? 'tr-TR' : 'en-GB'; }
			u.rate = V.rate;
			u.onend = u.onerror = function () { speaking = false; showSpeak(lastLine, false); setTimeout(next, 250); };
			window.speechSynthesis.speak(u);
		} catch (e) { speaking = false; }
	}
	function say(text, kind) {
		if (!text || !V.on || (kind && V.read[kind] === false)) { return; }
		text = String(text).trim();
		if (!text || text === speakQ[speakQ.length - 1]) { return; }
		if (speakQ.length > 3) { speakQ.splice(0, speakQ.length - 3); } // never fall far behind
		speakQ.push(text); next();
	}
	function stopTalking() { speakQ = []; speaking = false; try { window.speechSynthesis.cancel(); } catch (e) {} }

	/* settings popover */
	var pop = $('wl-vpop');
	function paintVoice() {
		$('wl-voice').setAttribute('aria-pressed', V.on ? 'true' : 'false');
		$('wl-voice-t').textContent = V.on ? tr().on : tr().off;
		$('v-on').checked = V.on;
		Array.prototype.forEach.call(pop.querySelectorAll('[data-read]'), function (c) { c.checked = V.read[c.getAttribute('data-read')] !== false; });
		$('v-lang').value = V.lang;
		$('v-rate').value = V.rate; $('v-rate-t').textContent = (+V.rate).toFixed(1) + '×';
		if (!V.on) { $('wl-speak').hidden = true; }
	}
	function fillVoices() {
		if (!window.speechSynthesis) { return; }
		var sel = $('v-voice'), all = window.speechSynthesis.getVoices() || [];
		sel.textContent = '';
		var d = document.createElement('option'); d.value = ''; d.textContent = V.lang === 'tr' ? 'Tarayıcı varsayılanı' : 'Browser default'; sel.appendChild(d);
		all.filter(function (v) { return (v.lang || '').toLowerCase().indexOf(V.lang) === 0; }).forEach(function (v) {
			var o = document.createElement('option'); o.value = v.voiceURI; o.textContent = v.name + ' (' + v.lang + ')'; sel.appendChild(o);
		});
		sel.value = V.voice;
		if (sel.value !== V.voice) { V.voice = ''; }
	}
	if (window.speechSynthesis) { window.speechSynthesis.onvoiceschanged = fillVoices; }
	$('wl-voice').addEventListener('click', function () {
		var open = pop.hidden;
		pop.hidden = !open;
		$('wl-voice').setAttribute('aria-expanded', open ? 'true' : 'false');
		if (open) { fillVoices(); $('v-on').focus(); }
	});
	$('v-done').addEventListener('click', function () { pop.hidden = true; $('wl-voice').setAttribute('aria-expanded', 'false'); $('wl-voice').focus(); });
	document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !pop.hidden) { pop.hidden = true; $('wl-voice').setAttribute('aria-expanded', 'false'); } });
	$('v-on').addEventListener('change', function () {
		V.on = this.checked; saveV(); paintVoice();
		if (V.on) { say(tr().hello); } else { stopTalking(); }
	});
	Array.prototype.forEach.call(pop.querySelectorAll('[data-read]'), function (c) {
		c.addEventListener('change', function () { V.read[c.getAttribute('data-read')] = c.checked; saveV(); });
	});
	$('v-voice').addEventListener('change', function () { V.voice = this.value; saveV(); });
	$('v-rate').addEventListener('input', function () { V.rate = +this.value; $('v-rate-t').textContent = V.rate.toFixed(1) + '×'; saveV(); });
	$('v-lang').addEventListener('change', function () {
		V.lang = this.value === 'tr' ? 'tr' : 'en'; V.voice = ''; saveV(); fillVoices(); paintVoice();
		fetch(C.voiceApi, { method: 'POST', credentials: 'same-origin', headers: { 'X-WP-Nonce': C.nonce, 'Content-Type': 'application/json' }, body: JSON.stringify({ lang: V.lang }) }).catch(function () {});
	});
	$('v-test').addEventListener('click', function () {
		if (!V.on) { V.on = true; saveV(); paintVoice(); }
		stopTalking(); say(tr().test);
	});
	$('wl-repeat').addEventListener('click', function () { if (lastLine) { stopTalking(); say(lastLine); } });
	$('wl-skip').addEventListener('click', function () { try { window.speechSynthesis.cancel(); } catch (e) {} });
	paintVoice();

	/* ---------- tabs ---------- */
	function tab(name) {
		Array.prototype.forEach.call(document.querySelectorAll('.wl-tabs button'), function (b) {
			b.setAttribute('aria-selected', b.getAttribute('data-tab') === name ? 'true' : 'false');
		});
		Array.prototype.forEach.call(document.querySelectorAll('.wl-panel'), function (p) {
			p.hidden = p.getAttribute('data-panel') !== name;
		});
	}
	Array.prototype.forEach.call(document.querySelectorAll('.wl-tabs button'), function (b) {
		b.addEventListener('click', function () { tab(b.getAttribute('data-tab')); });
	});
	$('s-wait-chip').addEventListener('click', function (e) { e.preventDefault(); tab('approve'); });

	/* ---------- follow ---------- */
	function setFollow(on) {
		follow = on;
		var b = $('wl-follow');
		b.classList.toggle('is-on', on);
		b.setAttribute('aria-pressed', on ? 'true' : 'false');
		b.querySelector('span').textContent = on ? 'Following Claude' : 'Browsing freely';
		if (on) { hideToast(); } else { showScreen(false); }
	}
	$('wl-follow').addEventListener('click', function () {
		setFollow(!follow);
		if (follow && toastUrl) { go(toastUrl, true); }
	});

	/* ---------- the site frame ---------- */
	function bust(u) { return u + (u.indexOf('?') > -1 ? '&' : '?') + 'fbcc_r=' + Date.now(); }
	function clean(u) { return String(u).replace(/[?&]fbcc_r=\d+/, '').replace(/\?$/, ''); }
	function go(url, flash) {
		if (!url) { return; }
		ourNav = true;
		curUrl = url;
		frame.src = bust(url);
		if (flash) {
			var f = frame.parentNode;
			f.classList.add('is-fresh');
			setTimeout(function () { f.classList.remove('is-fresh'); }, 2200);
		}
	}
	$('wl-back').addEventListener('click', function () { try { frame.contentWindow.history.back(); } catch (e) {} });
	$('wl-fwd').addEventListener('click', function () { try { frame.contentWindow.history.forward(); } catch (e) {} });
	$('wl-reload').addEventListener('click', function () { ourNav = true; try { frame.contentWindow.location.reload(); } catch (e) { go(curUrl); } });

	function blocks(doc) {
		var box = doc.querySelector('.entry-content, .wp-block-post-content, main article, main');
		if (!box) { return []; }
		var kids = function (el) { return Array.prototype.slice.call(el.children).filter(function (c) { return (c.innerText || '').trim().length > 0; }); };
		var list = kids(box);
		// Step inside a single wrapper (e.g. one Group block holding the whole page) so each block is compared on its own.
		for (var depth = 0; depth < 3 && list.length === 1 && list[0].children.length > 1; depth++) { list = kids(list[0]); }
		return list;
	}

	frame.addEventListener('load', function () {
		var doc, loc;
		try { doc = frame.contentDocument; loc = frame.contentWindow.location; } catch (e) { return; }
		if (!doc || !loc) { return; }
		var url = clean(loc.href);
		$('wl-path').textContent = clean(loc.pathname + loc.search) || '/';
		$('wl-newtab').href = url;
		if (!ourNav) { setFollow(false); }   // you clicked around inside the site
		ourNav = false;
		curUrl = url;

		// hide the admin bar inside the preview; style Claude's change markers
		var st = doc.createElement('style');
		st.textContent = 'html{margin-top:0!important}#wpadminbar{display:none!important}' +
			'.fbcc-chg{outline:2px solid #D97757!important;outline-offset:6px;border-radius:4px;position:relative;transition:outline-color 1s}' +
			'.fbcc-tag{position:absolute;right:-6px;top:-30px;background:#D97757;color:#141413;font:700 11px/1.4 ui-monospace,Menlo,Consolas,monospace;padding:4px 9px;border-radius:4px;z-index:9999;white-space:nowrap}';
		doc.head.appendChild(st);

		// compare with what this page looked like before → mark changed blocks
		var key = loc.pathname + loc.search.replace(/[?&]fbcc_r=\d+/, '');
		var els = blocks(doc);
		var texts = els.map(function (el) { return (el.innerText || '').trim(); });
		var before = snapshots[key];
		if (before) {
			var first = null, time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
			els.forEach(function (el, i) {
				if (before.indexOf(texts[i]) === -1) {
					el.classList.add('fbcc-chg');
					var tag = doc.createElement('span');
					tag.className = 'fbcc-tag';
					tag.textContent = 'CLAUDE CHANGED THIS · ' + time;
					el.appendChild(tag);
					first = first || el;
				}
			});
			if (first) { try { first.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (e) {} }
		}
		snapshots[key] = texts;
	});

	/* ---------- toast ---------- */
	function toast(text, url) {
		toastUrl = url;
		$('wl-toast-t').textContent = text;
		$('wl-toast').hidden = false;
	}
	function hideToast() { $('wl-toast').hidden = true; }
	$('wl-toast-go').addEventListener('click', function () { setFollow(true); go(toastUrl, true); });
	$('wl-toast-x').addEventListener('click', hideToast);

	function siteChanged(title, url) {
		if (follow) { go(url, true); } else { toast('Claude just updated ' + title, url); }
	}

	/* ---------- dock + feed ---------- */
	function dock(win) {
		if (win === 'plugins') { win = 'site'; }
		if (win === 'admin' && lastBrowser.title) {
			var a = document.querySelector('.wl-dock-i[data-win="admin"]');
			if (a) { a.lastChild.textContent = 'Admin · ' + lastBrowser.title.replace(/ \(admin\)$/, '').slice(0, 28); }
		}
		Array.prototype.forEach.call(document.querySelectorAll('.wl-dock-i'), function (d) {
			d.classList.toggle('is-on', d.getAttribute('data-win') === win);
		});
	}
	var RES = { browser: 'browser', done: 'done', read: 'read', queued: 'waiting for you', ready: 'ready to launch', blocked: 'blocked', error: 'error', info: 'info' };
	function feedItem(ev) {
		var li = document.createElement('li');
		var t = document.createElement('time'); t.textContent = ev.time;
		var box = document.createElement('div');
		var c = document.createElement('code'); c.textContent = ev.tool;
		var p = document.createElement('p'); p.textContent = ev.summary;
		var s = document.createElement('small'); s.className = 'r-' + ev.result; s.textContent = RES[ev.result] || ev.result;
		box.appendChild(c); box.appendChild(p); box.appendChild(s);
		li.appendChild(t); li.appendChild(box);
		var feed = $('wl-feed');
		feed.insertBefore(li, feed.firstChild);
		while (feed.children.length > 60) { feed.removeChild(feed.lastChild); }

		var mini = $('wl-mini');
		var m = document.createElement('li');
		var mc = document.createElement('code'); mc.textContent = ev.time.slice(0, 5);
		var ms = document.createElement('span'); ms.textContent = ev.summary;
		m.appendChild(mc); m.appendChild(ms);
		mini.insertBefore(m, mini.firstChild);
		while (mini.children.length > 4) { mini.removeChild(mini.lastChild); }
	}

	function play() {
		if (playing || !queue.length) { return; }
		playing = true;
		var ev = queue.shift();
		dock(ev.win);
		feedItem(ev);
		if (ev.result === 'queued') { say(tr().wait(ev.summary), 'wait'); }
		if (ev.tool === 'browser' && ev.result === 'browser') {
			if (/^Opened /.test(ev.summary)) { say(tr().page(ev.summary.replace(/^Opened /, '').replace(/ \(admin\)$/, '')), 'step'); }
			else if (V.read.click) { var m = ev.summary.match(/^Clicked “(.*?)”/); if (m) { say(tr().click(m[1]), 'click'); } }
		}
		if (ev.result === 'done' && ['menu_set', 'site_settings', 'theme_settings_set', 'widgets_set'].indexOf(ev.tool) > -1) {
			siteChanged('the site settings', follow ? (C.home || curUrl) : (C.home || curUrl));
		}
		setTimeout(function () { playing = false; play(); }, queue.length > 6 ? 500 : 1200);
	}

	/* ---------- approvals ---------- */
	var pendKey = '', allArmed = false;
	function decide(id, decision) {
		return fetch(C.decide, {
			method: 'POST', credentials: 'same-origin',
			headers: { 'X-WP-Nonce': C.nonce, 'Content-Type': 'application/json' },
			body: JSON.stringify({ id: id, decision: decision })
		}).then(function (r) { return r.json(); }).catch(function () { return { ok: false, message: 'Network error — try again.' }; });
	}
	function renderPending(list) {
		$('wl-tab-n').textContent = list.length;
		$('wl-pend-empty').hidden = list.length > 0;
		var key = list.map(function (p) { return p.id + (p.lock ? 'L' : ''); }).join(',');
		if (key === pendKey) { return; }
		pendKey = key;
		var wrap = $('wl-pend');
		wrap.textContent = '';
		var open = list.filter(function (p) { return !p.lock; });
		if (open.length > 1) {
			var all = document.createElement('button');
			all.type = 'button'; all.className = 'wl-btn wl-all';
			all.textContent = 'Approve all ' + open.length + ' in order';
			all.addEventListener('click', function () {
				if (!allArmed) {
					allArmed = true;
					all.textContent = 'Click again to approve all ' + open.length;
					setTimeout(function () { allArmed = false; all.textContent = 'Approve all ' + open.length + ' in order'; }, 3500);
					return;
				}
				all.disabled = true; all.textContent = 'Approving…';
				var chain = Promise.resolve();
				open.forEach(function (p) { chain = chain.then(function () { return decide(p.id, 'approve'); }); });
				chain.then(function () { pendKey = ''; poll(); });
			});
			wrap.appendChild(all);
		}
		list.forEach(function (p) {
			var box = document.createElement('div'); box.className = 'wl-pend-item';
			var t = document.createElement('strong'); t.textContent = '#' + p.id + ' ' + p.title; box.appendChild(t);
			if (p.reason) { var r = document.createElement('small'); r.textContent = p.reason; box.appendChild(r); }
			if (p.lock) { var l = document.createElement('small'); l.className = 'bad'; l.textContent = p.lock; box.appendChild(l); }
			var row = document.createElement('div'); row.className = 'wl-pend-row';
			if (p.diff) { var a = document.createElement('a'); a.href = C.approve; a.target = '_blank'; a.rel = 'noopener'; a.textContent = 'View diff ↗'; row.appendChild(a); }
			var no = document.createElement('button'); no.type = 'button'; no.className = 'wl-btn ghost'; no.textContent = 'Reject';
			var yes = document.createElement('button'); yes.type = 'button'; yes.className = 'wl-btn'; yes.textContent = p.lock ? 'Approve (locked)' : 'Approve';
			yes.disabled = !!p.lock;
			function act(decision) {
				yes.disabled = no.disabled = true;
				(decision === 'approve' ? yes : no).textContent = decision === 'approve' ? 'Approving…' : 'Rejecting…';
				decide(p.id, decision).then(function (res) {
					var m = document.createElement('p'); m.className = 'wl-pend-msg ' + (res.ok ? 'ok' : 'bad'); m.textContent = res.message; box.appendChild(m);
					if (!res.ok) { yes.disabled = !!p.lock; no.disabled = false; yes.textContent = 'Approve'; no.textContent = 'Reject'; }
					setTimeout(function () { pendKey = ''; poll(); }, res.ok ? 900 : 0);
				});
			}
			yes.addEventListener('click', function () { act('approve'); });
			no.addEventListener('click', function () { act('reject'); });
			row.appendChild(no); row.appendChild(yes);
			box.appendChild(row);
			wrap.appendChild(box);
		});
	}

	/* ---------- approved build plan ---------- */
	var planKey = '';
	function renderPlan(pl) {
		var box = $('wl-planbox');
		if (!pl) { box.hidden = true; planKey = ''; return; }
		var key = [pl.id, pl.built, pl.live, pl.ready, pl.status].join('|');
		if (key === planKey) { return; }
		planKey = key;
		box.hidden = false;
		box.textContent = '';
		var h = document.createElement('strong'); h.textContent = 'Approved plan #' + pl.id + ': ' + pl.title; box.appendChild(h);
		var s1 = document.createElement('small');
		s1.textContent = pl.built + '/' + pl.pages + ' pages built · ' + pl.live + ' live · go live ' + (pl.launch === 'auto' ? 'automatically' : 'with one Launch click');
		box.appendChild(s1);
		var s2 = document.createElement('small'); s2.textContent = 'Inside this plan Claude does not need to ask you again.'; box.appendChild(s2);
		if (pl.ready > 0) {
			var b = document.createElement('button'); b.type = 'button'; b.className = 'wl-btn wl-all';
			b.textContent = 'Launch ' + pl.ready + ' item(s) now';
			b.addEventListener('click', function () {
				b.disabled = true; b.textContent = 'Launching…';
				fetch(C.launch, { method: 'POST', credentials: 'same-origin', headers: { 'X-WP-Nonce': C.nonce } })
					.then(function (r) { return r.json(); })
					.then(function (res) {
						var m = document.createElement('p'); m.className = 'wl-pend-msg ' + (res.ok ? 'ok' : 'bad'); m.textContent = res.message; box.appendChild(m);
						planKey = ''; setTimeout(poll, 600);
						if (res.ok) { siteChanged('the whole site', C.home); }
					});
			});
			box.appendChild(b);
		}
	}

	/* ---------- Claude's browser (0.7.0) ---------- */
	var sframe = $('wl-screen-f'), sbox = $('wl-screen');
	function showScreen(on) {
		if (on === screenOn) { return; }
		screenOn = on;
		sbox.hidden = !on;
		$('wl-src').hidden = !on;
		root.classList.toggle('is-browser', on);
		if (!on) { $('wl-path').textContent = curUrl ? clean(curUrl).replace(/^https?:\/\/[^/]+/, '') || '/' : '/'; }
	}
	var lastW = 0;
	function fitScreen(w) {
		if (w) { lastW = w; }
		w = lastW;
		var box = sbox.getBoundingClientRect();
		var scale = Math.min(1, box.width / Math.max(320, w || box.width));
		sframe.style.width = (box.width / scale) + 'px';
		sframe.style.height = (box.height / scale) + 'px';
		sframe.style.transform = 'scale(' + scale + ')';
	}
	var MARK = '<style>[data-fbcc-clicked]{outline:3px solid #D97757!important;outline-offset:4px!important;border-radius:4px}' +
		'[data-fbcc-clicked]::after{content:"CLAUDE CLICKED THIS";position:absolute;margin:-26px 0 0 8px;background:#D97757;color:#141413;font:800 10.5px/1.6 ui-monospace,Menlo,Consolas,monospace;padding:2px 8px;border-radius:4px;z-index:99999;white-space:nowrap}' +
		'html{scroll-behavior:auto!important}</style>';
	function loadScreen() {
		fetch(C.screen + '?since=' + screenSeq, { credentials: 'same-origin', headers: { 'X-WP-Nonce': C.nonce } })
			.then(function (r) { return r.ok ? r.json() : null; })
			.then(function (s) {
				if (!s || s.same || !s.html) { return; }
				screenSeq = s.seq;
				var html = s.html.replace(/<\/head>/i, MARK + '</head>');
				// keep Claude's scroll position: jump there once the copy has painted
				sframe.onload = function () {
					try { sframe.contentWindow.scrollTo(s.sx | 0, s.sy | 0); } catch (e) { /* the copy starts at the top */ }
				};
				sframe.srcdoc = html;
				fitScreen(s.w);
				$('wl-path').textContent = clean(s.url).replace(/^https?:\/\/[^/]+/, '') || '/';
				$('wl-newtab').href = s.url;
			})
			.catch(function () {});
	}
	window.addEventListener('resize', function () { if (screenOn) { fitScreen(); } });

	function browserFollow(d) {
		var b = d.browser || {};
		if (b.lang && b.lang !== V.lang) { V.lang = b.lang; paintVoice(); }
		if (!b.linked) { showScreen(false); return; }
		if (b.time > lastBrowser.time) { lastBrowser = { time: b.time, url: b.url, title: b.title }; }
		// Claude's browser leads while it is the most recent thing Claude did (and it did something in the last 2 minutes).
		var leads = b.time && (d.now - b.time) < 120 && b.time >= lastConnectorTs;
		if (follow && leads) {
			showScreen(true);
			dock('admin');
			if (b.screen && b.screen !== screenSeq) { loadScreen(); }
		} else if (!leads) {
			showScreen(false);
		}
	}

	/* ---------- top bar + plan ---------- */
	function paint(d) {
		var t = d.task;
		root.classList.toggle('is-live', !!d.live);
		$('wl-state').textContent = d.live ? 'LIVE' : 'IDLE';
		if (t) {
			var total = t.total || 1, step = t.step || 0;
			$('wl-step').textContent = 'STEP ' + step + '/' + total;
			$('wl-title').textContent = t.note || t.title;
			$('wl-bar').style.width = Math.min(100, Math.round(100 * step / total)) + '%';
			$('wl-plan-t').textContent = t.title || 'Working';
			var plan = $('wl-plan'); plan.textContent = '';
			(t.steps || []).forEach(function (line, i) {
				var li = document.createElement('li');
				li.className = (i + 1 < step) ? 'done' : (i + 1 === step ? 'now' : '');
				li.textContent = line;
				plan.appendChild(li);
			});
			var k = t.title + '|' + step + '|' + t.note + '|' + (t.brief || '');
			if (k !== lastTaskKey) {
				var isNew = lastTaskKey.split('|')[0] !== t.title;
				lastTaskKey = k;
				if (isNew) { say(t.brief || tr().start(C.name, t.title, total, (t.steps || [])[0] || t.note), 'start'); }
				else { say(t.brief || t.note || t.title, 'step'); }
			}
		} else {
			if (d.finished && lastFinish !== d.finished.updated) {
				if (lastFinish !== null) { say(d.finished.brief || tr().done(d.finished.note), 'finish'); }
				lastFinish = d.finished.updated;
			} else if (!d.finished && lastFinish === null) { lastFinish = 0; }
			lastTaskKey = '';
			$('wl-step').textContent = '';
			$('wl-title').textContent = 'Nothing is running. Start a task in Claude and watch it here.';
			$('wl-bar').style.width = '0';
			$('wl-plan-t').textContent = 'Waiting for a task';
		}
		$('s-calls').textContent = d.stats.calls;
		$('s-tokens').textContent = d.stats.tokens >= 1000 ? (d.stats.tokens / 1000).toFixed(1) + 'k' : d.stats.tokens;
		$('s-wait').textContent = d.stats.waiting;
		$('s-wait-chip').classList.toggle('has', d.stats.waiting > 0);
		$('wl-level').textContent = (d.plan ? 'Plan #' + d.plan.id + ' · ' : '') + (LEVELS[d.level] || d.level) + (d.locks && d.locks.emergency ? ' · write lock on' : '') + (d.locks && d.locks.lab ? ' · lab lock on' : '');

		var waitingNow = d.pending.length + (d.plan ? d.plan.ready : 0);
		if (waitingNow > lastPendCount && lastPendCount >= 0) { tab('approve'); }
		lastPendCount = waitingNow;
		renderPending(d.pending || []);
		renderPlan(d.plan);
		$('wl-pend-empty').hidden = (d.pending || []).length > 0 || !!(d.plan && d.plan.ready);

		browserFollow(d);

		if (d.preview && d.preview.stamp !== lastStamp) {
			var firstTime = lastStamp === '';
			lastStamp = d.preview.stamp;
			if (!firstTime) { siteChanged('“' + d.preview.title + '”', d.preview.url); }
		}
	}

	function poll() {
		if (document.hidden) { return; }
		fetch(C.api + '?since=' + last, { credentials: 'same-origin', headers: { 'X-WP-Nonce': C.nonce } })
			.then(function (r) { return r.ok ? r.json() : null; })
			.then(function (d) {
				if (!d) { return; }
				var first = last === 0;
				last = d.last || last;
				paint(d);
				(d.events || []).forEach(function (ev) {
					if (ev.tool !== 'browser' && ev.ts > lastConnectorTs) { lastConnectorTs = ev.ts; }
					if (first) { feedItem(ev); } else { queue.push(ev); }
				});
				if (first && d.events.length) { dock(d.events[d.events.length - 1].win); }
				play();
			})
			.catch(function () {});
	}

	go(C.home, false);
	poll();
	setInterval(poll, 1500);
})();
