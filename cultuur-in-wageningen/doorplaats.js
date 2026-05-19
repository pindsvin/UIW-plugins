/* doorplaats.js — Cultuur in Wageningen v1.4.0
 * Stuurt het formulier rechtstreeks vanuit de browser naar de CF7 REST API.
 * 'ciw' (ajaxUrl, cfApiUrl, saveNonce, postId) is beschikbaar via inline <script> in de PHP-pagina.
 */
document.addEventListener('DOMContentLoaded', function () {
    var form       = document.getElementById('ciw-doorplaats-form');
    var acceptance = document.getElementById('ciw-acceptance');
    var submitBtn  = document.getElementById('ciw-submit-btn');
    var spinner    = document.getElementById('ciw-spinner');
    var result     = document.getElementById('ciw-result');
    var fileInput  = document.getElementById('ciw-afbeelding');

    if (!form) return;

    /* Verzendknop alleen actief als voorwaarden aangevinkt zijn */
    acceptance.addEventListener('change', function () {
        submitBtn.disabled = !this.checked;
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        /* Client-side bestandsgrootte-check */
        if (fileInput && fileInput.files.length > 0) {
            if (fileInput.files[0].size > 1024 * 1024) {
                showError('De afbeelding is groter dan 1 MB. Kies een kleinere afbeelding.');
                return;
            }
        }

        submitBtn.disabled = true;
        spinner.style.visibility = 'visible';
        result.innerHTML = '';

        var formData = new FormData(form);

        fetch(ciw.cfApiUrl, {
            method: 'POST',
            body:   formData,
            /* Geen Content-Type header — browser stelt deze in met correct boundary */
        })
        .then(function (response) {
            return response.json();
        })
        .then(function (data) {
            spinner.style.visibility = 'hidden';

            if (data.status === 'mail_sent') {
                /* Succes */
                form.style.display = 'none';
                result.innerHTML =
                    '<div class="notice notice-success inline" style="padding:12px 16px;">' +
                    '<p style="margin:0;font-size:15px;">' +
                    '&#10003; <strong>Geplaatst!</strong> Het bericht is verstuurd naar Cultuur in Wageningen.' +
                    '</p>' +
                    '<p style="margin:8px 0 0;color:#555;">Dit tabblad kan worden gesloten.</p>' +
                    '</div>';

                /* Sla tijdstip op in WordPress (fire-and-forget) */
                fetch(ciw.ajaxUrl, {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body:    new URLSearchParams({
                        action:  'cultuur_wageningen_save_submitted',
                        nonce:   ciw.saveNonce,
                        post_id: ciw.postId,
                    }).toString(),
                });

            } else {
                /* Mislukt — toon foutmelding */
                var msg    = (data.message || '').replace(/<[^>]*>/g, '').trim();
                var status = data.status || 'onbekend';

                var invalid = '';
                if (data.invalid_fields && data.invalid_fields.length) {
                    var names = data.invalid_fields.map(function (f) { return f.field; });
                    invalid = ' Ongeldige velden: ' + escHtml(names.join(', ')) + '.';
                }

                showError(
                    'Verzending mislukt [' + escHtml(status) + ']:' +
                    invalid +
                    (msg ? ' ' + escHtml(msg) : '')
                );
                submitBtn.disabled = false;
            }
        })
        .catch(function () {
            spinner.style.visibility = 'hidden';
            showError('Verbindingsfout. Controleer je internetverbinding en probeer opnieuw.');
            submitBtn.disabled = false;
        });
    });

    function showError(msg) {
        result.innerHTML =
            '<div class="notice notice-error inline" style="padding:12px 16px;">' +
            '<p style="margin:0;">' + msg + '</p>' +
            '</div>';
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }
});
