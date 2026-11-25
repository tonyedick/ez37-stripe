/* EZ37 component behaviour: tabs, icon gallery (filter + copy name), form validation demo.
   No dependencies. Safe to include more than once. Embedded inline by the page scripts. */
(function () {
	if (window.__ez37Components) { return; }
	window.__ez37Components = true;

	/* ---------- Tabs: role=tablist, arrow keys, Home/End ---------- */
	function initTabs(root) {
		var tabs = Array.prototype.slice.call(root.querySelectorAll('[role="tab"]'));
		var panels = tabs.map(function (t) { return document.getElementById(t.getAttribute('aria-controls')); });
		function select(i, focus) {
			tabs.forEach(function (t, n) {
				var on = n === i;
				t.setAttribute('aria-selected', on ? 'true' : 'false');
				t.tabIndex = on ? 0 : -1;
				if (panels[n]) { panels[n].hidden = !on; }
			});
			if (focus) { tabs[i].focus(); }
		}
		tabs.forEach(function (t, i) {
			t.addEventListener('click', function () { select(i, false); });
			t.addEventListener('keydown', function (e) {
				var vertical = root.classList.contains('ez37-tabs--vertical');
				var next = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 }[e.key];
				if (!vertical && (e.key === 'ArrowUp' || e.key === 'ArrowDown')) { next = undefined; }
				if (next) { e.preventDefault(); select((i + next + tabs.length) % tabs.length, true); }
				else if (e.key === 'Home') { e.preventDefault(); select(0, true); }
				else if (e.key === 'End') { e.preventDefault(); select(tabs.length - 1, true); }
			});
		});
		var start = tabs.findIndex(function (t) { return t.getAttribute('aria-selected') === 'true'; });
		select(start < 0 ? 0 : start, false);
	}

	/* ---------- Icon gallery: filter + click to copy the icon name ---------- */
	function initIcons(root) {
		var input = root.querySelector('[data-ez37-icon-filter]');
		var tiles = Array.prototype.slice.call(root.querySelectorAll('[data-icon]'));
		var live = root.querySelector('[data-ez37-icon-live]');
		if (input) {
			input.addEventListener('input', function () {
				var q = input.value.trim().toLowerCase();
				tiles.forEach(function (t) { t.hidden = q !== '' && t.getAttribute('data-icon').indexOf(q) === -1; });
			});
		}
		tiles.forEach(function (t) {
			t.addEventListener('click', function () {
				var name = t.getAttribute('data-icon');
				var done = function () { if (live) { live.textContent = 'Copied icon name: ' + name; } };
				if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(name).then(done, done); } else { done(); }
			});
		});
	}

	/* ---------- Form validation demo (library preview only; never submits) ---------- */
	function initForms(form) {
		var fields = Array.prototype.slice.call(form.querySelectorAll('.ez37-field'));
		var success = form.querySelector('[data-ez37-success]');
		function check(field) {
			var ctl = field.querySelector('input, select, textarea');
			if (!ctl || !ctl.required) { return true; }
			var ok = ctl.type === 'checkbox' ? ctl.checked : ctl.checkValidity() && String(ctl.value).trim() !== '';
			field.classList.toggle('is-error', !ok);
			return ok;
		}
		fields.forEach(function (f) {
			var ctl = f.querySelector('input, select, textarea');
			if (ctl) {
				ctl.addEventListener('input', function () { if (f.classList.contains('is-error')) { check(f); } });
				ctl.addEventListener('change', function () { if (f.classList.contains('is-error')) { check(f); } });
			}
		});
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var ok = true;
			fields.forEach(function (f) { if (!check(f)) { ok = false; } });
			if (success) { success.hidden = !ok; }
			if (!ok) {
				var bad = form.querySelector('.is-error input, .is-error select, .is-error textarea');
				if (bad) { bad.focus(); }
			}
		});
	}

	function init() {
		document.querySelectorAll('[data-ez37-tabs]').forEach(initTabs);
		document.querySelectorAll('[data-ez37-icons]').forEach(initIcons);
		document.querySelectorAll('form[data-ez37-demo]').forEach(initForms);
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
