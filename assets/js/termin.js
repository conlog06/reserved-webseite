/* reserved.social – Terminbuchung (/termin/)
   1. Terminart  2. Tag + Uhrzeit  3. Daten → Bestätigung mit Kalendereintrag */
(() => {
  'use strict';
  const $ = (s, c = document) => c.querySelector(s);
  const form = $('#bkForm');
  if (!form) return;
  const root = document.documentElement;
  const LIVE = root.hasAttribute('data-cms-live');
  const API = root.dataset.api || '../_reserved/api.php';
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const err = (m) => { const el = $('#bkError'); el.textContent = m; el.hidden = !m; };
  let src = 'direkt';
  try { src = sessionStorage.getItem('rs-src') || new URLSearchParams(location.search).get('utm_source') || 'direkt'; } catch (e) {}

  if (!LIVE) { form.hidden = true; $('#bkOff').hidden = false; return; }

  const st = { types: [], art: new URLSearchParams(location.search).get('art') || '', days: [], date: '', time: '' };
  const typeOf = (id) => st.types.find((t) => t.id === id);

  const renderTypes = () => {
    const box = $('#bkTypes');
    box.innerHTML = st.types.map((t) => `<label class="bk-type"><input type="radio" name="art" value="${esc(t.id)}"${t.id === st.art ? ' checked' : ''}><span><b>${esc(t.name)}</b>${t.desc ? `<small>${esc(t.desc)}</small>` : ''}<em><i><svg><use href="#i-clock"/></svg>${t.min} Min.</i>${t.where ? `<i><svg><use href="#i-pin"/></svg>${esc(t.where)}</i>` : ''}</em></span></label>`).join('');
    $('#bkStepArt').hidden = st.types.length < 2;
    box.querySelectorAll('input').forEach((i) => i.addEventListener('change', () => { st.art = i.value; st.date = st.time = ''; load(); }));
  };
  const renderDays = () => {
    const box = $('#bkDays');
    $('#bkEmpty').hidden = st.days.length > 0;
    box.innerHTML = st.days.map((d) => `<button type="button" class="bk-day" role="radio" aria-checked="${d.date === st.date}" data-date="${d.date}"><small>${esc(d.wd)}</small><b>${esc(d.d)}</b><i>${esc(d.m)}</i></button>`).join('');
    box.querySelectorAll('.bk-day').forEach((b) => b.addEventListener('click', () => { st.date = b.dataset.date; st.time = ''; renderDays(); renderTimes(); }));
    const on = box.querySelector('[aria-checked="true"]');
    if (on) on.scrollIntoView({ block: 'nearest', inline: 'nearest' });
    navState();
  };
  const renderTimes = () => {
    const d = st.days.find((x) => x.date === st.date);
    $('#bkTimes').innerHTML = d ? d.slots.map((s) => `<button type="button" class="bk-time" role="radio" aria-checked="${s === st.time}" data-time="${s}">${s}</button>`).join('') : '';
    $('#bkTimes').querySelectorAll('.bk-time').forEach((b) => b.addEventListener('click', () => {
      st.time = b.dataset.time; renderTimes(); showData(true);
    }));
    showData(!!st.time);
  };
  const showData = (scroll) => {
    const box = $('#bkData'), t = typeOf(st.art), d = st.days.find((x) => x.date === st.date);
    box.hidden = !(st.time && d && t);
    if (box.hidden) return;
    $('#bkSum').innerHTML = `<span><svg><use href="#i-cal"/></svg>${esc(d.wd)}, ${esc(d.d)}. ${esc(d.m)}</span><span><svg><use href="#i-clock"/></svg>${esc(st.time)} Uhr · ${t.min} Min.</span><span>${esc(t.name)}</span>`;
    if (scroll === true && innerWidth < 1021) box.scrollIntoView({ behavior: 'smooth', block: 'start' });
  };
  const days = $('#bkDays');
  const navState = () => { $('#bkPrev').disabled = days.scrollLeft < 4; $('#bkNext').disabled = days.scrollLeft + days.clientWidth >= days.scrollWidth - 4; };
  days.addEventListener('scroll', navState, { passive: true });
  $('#bkPrev').addEventListener('click', () => days.scrollBy({ left: -days.clientWidth * 0.8 }));
  $('#bkNext').addEventListener('click', () => days.scrollBy({ left: days.clientWidth * 0.8 }));

  const load = async () => {
    try {
      const res = await fetch(`${API}?a=termine&art=${encodeURIComponent(st.art)}`, { cache: 'no-store' });
      const j = await res.json();
      if (!j.ok) throw new Error(j.error || '');
      st.types = j.types; st.art = j.art; st.days = j.days || [];
      if (!st.days.find((d) => d.date === st.date)) st.date = st.days[0]?.date || '';
      renderTypes(); renderDays(); renderTimes();
    } catch (e) {
      $('#bkTypes').innerHTML = `<p class="bk-empty">${esc(e.message || 'Die Termine konnten nicht geladen werden.')} <a href="../#kontakt">Schreib uns gern eine Nachricht.</a></p>`;
    }
  };
  load();

  form.addEventListener('submit', async (e) => {
    e.preventDefault(); err('');
    const f = form.elements;
    if (!st.time) return err('Bitte wähle einen Tag und eine Uhrzeit.');
    if (!f.name.value.trim()) { f.name.focus(); return err('Wie heißt du?'); }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(f.email.value.trim())) { f.email.focus(); return err('Bitte gib eine gültige E-Mail-Adresse an.'); }
    const btn = $('#bkSubmit'); btn.disabled = true;
    try {
      const res = await fetch(API + '?a=buchen', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({
        art: st.art, date: st.date, time: st.time, name: f.name.value.trim(), email: f.email.value.trim(), phone: f.phone.value.trim(),
        firma: f.firma.value.trim(), note: f.note.value.trim(), website: f.website.value, src }) });
      const j = await res.json().catch(() => ({}));
      if (!res.ok || !j.ok) {
        btn.disabled = false;
        if (res.status === 400 && /vergeben/.test(j.error || '')) { st.time = ''; await load(); }
        return err(j.error || 'Das hat leider nicht geklappt. Versuch es bitte noch einmal.');
      }
      form.hidden = true;
      $('#bkDoneWhen').textContent = `${j.type} · ${j.when}`;
      $('#bkDoneMail').textContent = f.email.value.trim();
      $('#bkIcs').href = j.ics;
      const d = $('#bkDone'); d.hidden = false; d.focus();
      d.scrollIntoView({ behavior: 'smooth', block: 'center' });
    } catch (x) {
      btn.disabled = false; err('Keine Verbindung. Versuch es bitte noch einmal.');
    }
  });
})();
