/* FB AI Engine – Claude Connector 0.7.0
 * Runs ONLY in the browser the owner linked as "Claude's browser".
 * Reports the page, clicks and saves to Watch Me Live, and sends a sanitised copy of the screen.
 * Never copies: scripts, password values, hidden fields, nonces, anything inside .fbcc-private.
 */
(function () {
	'use strict';
	var A = window.FBCC_AGENT;
	if (!A || !window.fetch || window.top !== window) { return; } // not inside frames (e.g. the Customizer preview)

	function post(url, body) {
		try {
			return fetch(url, {
				method: 'POST', credentials: 'same-origin', keepalive: body && JSON.stringify(body).length < 60000,
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': A.nonce },
				body: JSON.stringify(body)
			}).catch(function () {});
		} catch (e) { return null; }
	}
	function title() { return document.title || location.pathname; }

	/* ---------- badge so everyone can see this tab is mirrored ---------- */
	function badge() {
		var b = document.createElement('div');
		b.className = 'fbcc-private fbcc-agent-badge';
		b.setAttribute('role', 'status');
		b.textContent = 'Claude’s browser · mirrored to Watch Me Live';
		b.style.cssText = 'position:fixed;left:12px;bottom:12px;z-index:2147483647;background:#141413;color:#FAF9F5;font:600 12px/1.2 system-ui,sans-serif;padding:7px 11px;border-radius:99px;box-shadow:0 6px 20px rgba(0,0,0,.25);pointer-events:none';
		document.body.appendChild(b);
	}

	/* ---------- what did Claude click? ---------- */
	function labelOf(el) {
		if (!el || el.nodeType !== 1) { return ''; }
		var t = el.getAttribute('aria-label') || el.getAttribute('title') || '';
		if (!t && (el.tagName === 'INPUT') && /^(submit|button|reset)$/i.test(el.type)) { t = el.value; }
		if (!t) { t = (el.innerText || el.textContent || '').trim(); }
		if (!t && el.tagName === 'IMG') { t = el.alt; }
		return String(t).replace(/\s+/g, ' ').trim().slice(0, 80);
	}
	function target(el) {
		while (el && el !== document.body) {
			if (el.matches && el.matches('a,button,input[type=submit],input[type=button],input[type=checkbox],input[type=radio],select,label,[role=button],[role=tab],[role=menuitem],summary')) { return el; }
			el = el.parentElement;
		}
		return null;
	}
	var lastClicked = null;
	document.addEventListener('click', function (e) {
		var el = target(e.target);
		if (!el || el.closest('.fbcc-private')) { return; }
		if (el.tagName === 'INPUT' && /password/i.test(el.type)) { return; }
		if (lastClicked) { lastClicked.removeAttribute('data-fbcc-clicked'); }
		el.setAttribute('data-fbcc-clicked', String(Date.now()));
		lastClicked = el;
		post(A.event, { type: 'click', url: location.href, title: title(), label: labelOf(el) });
		snapSoon(700);
	}, true);
	document.addEventListener('submit', function (e) {
		var f = e.target;
		var btn = f && f.querySelector('[type=submit]');
		post(A.event, { type: 'submit', url: location.href, title: title(), label: btn ? labelOf(btn) : '' });
	}, true);

	/* ---------- the screen copy ---------- */
	function abs(u) { try { return new URL(u, location.href).href; } catch (e) { return u; } }
	function scrub(doc, root) {
		var kill = root.querySelectorAll('script,noscript,template,iframe[src^="javascript"],object,embed,.fbcc-private,#wpadminbar .ab-sub-wrapper');
		Array.prototype.forEach.call(kill, function (n) { n.parentNode && n.parentNode.removeChild(n); });
		Array.prototype.forEach.call(root.querySelectorAll('*'), function (n) {
			for (var i = n.attributes.length - 1; i >= 0; i--) {
				var a = n.attributes[i].name;
				if (/^on/i.test(a)) { n.removeAttribute(a); }
			}
			if (n.tagName === 'A' && /^\s*javascript:/i.test(n.getAttribute('href') || '')) { n.setAttribute('href', '#'); }
		});
		Array.prototype.forEach.call(root.querySelectorAll('input'), function (n) {
			var t = (n.getAttribute('type') || 'text').toLowerCase();
			if (t === 'password' || t === 'hidden' || /nonce|token|secret|key/i.test(n.getAttribute('name') || '')) { n.removeAttribute('value'); }
		});
	}
	function serialize(doc, depth) {
		var clone = doc.documentElement.cloneNode(true);
		// live form state → attributes, so the copy shows what Claude typed or picked
		var live = doc.querySelectorAll('input,textarea,select');
		var copy = clone.querySelectorAll('input,textarea,select');
		for (var i = 0; i < live.length && i < copy.length; i++) {
			var s = live[i], c = copy[i];
			var t = (s.type || '').toLowerCase();
			if (t === 'password' || t === 'hidden') { continue; }
			if (s.tagName === 'TEXTAREA') { c.textContent = s.value; }
			else if (s.tagName === 'SELECT') {
				Array.prototype.forEach.call(c.options, function (o, j) { if (s.options[j] && s.options[j].selected) { o.setAttribute('selected', ''); } else { o.removeAttribute('selected'); } });
			} else if (t === 'checkbox' || t === 'radio') { if (s.checked) { c.setAttribute('checked', ''); } else { c.removeAttribute('checked'); } }
			else { c.setAttribute('value', s.value); }
		}
		// same-site frames (e.g. the Customizer preview): inline one level deep
		if (depth < 1) {
			var lf = doc.querySelectorAll('iframe'), cf = clone.querySelectorAll('iframe');
			for (var k = 0; k < lf.length && k < cf.length; k++) {
				try {
					var fd = lf[k].contentDocument;
					if (fd && fd.documentElement) { cf[k].setAttribute('srcdoc', serialize(fd, depth + 1)); cf[k].removeAttribute('src'); }
				} catch (e) { /* other site: stays blank */ }
			}
		}
		scrub(doc, clone);
		var head = clone.querySelector('head');
		if (head) {
			var base = doc.createElement('base'); base.setAttribute('href', abs(doc.baseURI || location.href));
			head.insertBefore(base, head.firstChild);
		}
		return '<!doctype html>' + clone.outerHTML;
	}

	var lastHtml = '', timer = null, sending = false;
	function snap() {
		timer = null;
		if (sending || document.hidden) { return; }
		var html;
		try { html = serialize(document, 0); } catch (e) { return; }
		if (html === lastHtml) { return; }
		lastHtml = html;
		sending = true;
		var p = post(A.screen, {
			url: location.href, title: title(), html: html,
			w: window.innerWidth, h: window.innerHeight,
			sx: Math.round(window.scrollX), sy: Math.round(window.scrollY)
		});
		var done = function () { sending = false; };
		if (p && p.then) { p.then(done, done); } else { done(); }
	}
	function snapSoon(ms) { if (timer) { clearTimeout(timer); } timer = setTimeout(snap, ms || 1200); }

	var mo = new MutationObserver(function () { snapSoon(1500); });
	document.addEventListener('input', function () { snapSoon(1200); }, true);
	document.addEventListener('change', function () { snapSoon(600); }, true);
	window.addEventListener('scroll', function () { snapSoon(900); }, { passive: true });

	function start() {
		badge();
		post(A.event, { type: 'page', url: location.href, title: title() });
		snapSoon(400);
		mo.observe(document.documentElement, { subtree: true, childList: true, attributes: true, characterData: true });
		setInterval(function () { snapSoon(10); }, 8000); // catch canvas-free changes the observer missed
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', start); } else { start(); }
})();
