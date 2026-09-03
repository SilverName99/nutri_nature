/* Trimiterea formularului de contact. Endpointul /contact/send primeste JSON
   si raspunde JSON, deci trimiterea clasica prin submit nu ar functiona. */
(function () {
  var form = document.getElementById('formular-contact');
  if (!form) return;
  var raspuns = document.getElementById('cf-raspuns');
  var buton = form.querySelector('button[type="submit"]');

  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    if (!form.checkValidity()) { form.reportValidity(); return; }

    var date = {};
    new FormData(form).forEach(function (v, k) { date[k] = String(v).trim(); });

    buton.disabled = true;
    raspuns.className = 'mt-3 mb-0 text-secondary';
    raspuns.textContent = 'Se trimite…';

    fetch('/contact/send', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(date)
    })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, corp: j }; }); })
      .then(function (rez) {
        if (rez.ok && rez.corp && rez.corp.ok) {
          form.reset();
          raspuns.className = 'mt-3 mb-0 fw-semibold';
          raspuns.textContent = 'Mesajul a fost trimis. Vă răspundem în cel mai scurt timp.';
        } else {
          raspuns.className = 'mt-3 mb-0 text-danger fw-semibold';
          raspuns.textContent = (rez.corp && rez.corp.error) || 'Mesajul nu a putut fi trimis.';
        }
      })
      .catch(function () {
        raspuns.className = 'mt-3 mb-0 text-danger fw-semibold';
        raspuns.textContent = 'Conexiune întreruptă. Încercați din nou.';
      })
      .finally(function () { buton.disabled = false; });
  });
})();
