/* doorplaats.js — Cultuur in Wageningen v2.0.0
 * Stuurt het formulier via onze eigen WP AJAX-endpoint (server-side proxy).
 * 'ciw' (ajaxUrl, submitNonce, postId) is beschikbaar via inline <script> in de PHP-pagina.
 */
document.addEventListener('DOMContentLoaded', function () {
    var form       = document.getElementById('ciw-doorplaats-form');
    var acceptance = document.getElementById('ciw-acceptance');
    var submitBtn  = document.getElementById('ciw-submit-btn');
    var spinner    = document.getElementById('ciw-spinner');
    var result     = document.getElementById('ciw-result');
    var fileInput  = document.getElementById('ciw-afbeelding');

    if (!form) return;

    acceptance.addEventListener('change', function () {
        submitBtn.disabled = !this.checked;
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();

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
        formData.append('action', 'cultuur_wageningen_submit');
        formData.append('nonce',   ciw.submitNonce);
        formData.append('post_id', ciw.postId);

        fetch(ciw.ajaxUrl, {
            method: 'POST',
            body:   formData,
        })
        .then(function (response) {
            return response.json();
        })
        .then(function (envelope) {
            spinner.style.visibility = 'hidden';

            if (envelope.success) {
                form.style.display = 'none';
                result.innerHTML =
                    '<div class="notice notice-success inline" style="padding:12px 16px;">' +
                    '<p style="margin:0;font-size:15px;">' +
                    '&#10003; <strong>Geplaatst!</strong> Het bericht is verstuurd naar Cultuur in Wageningen.' +
                    '</p>' +
                    '<p style="margin:8px 0 0;color:#555;">Dit tabblad kan worden gesloten.</p>' +
                    '</div>';
            } else {
                var d       = envelope.data || {};
                var msg     = escHtml(d.message || 'Onbekende fout');
                var status  = d.status  ? ' [' + escHtml(d.status) + ']' : '';
                var invalid = '';
                if (d.invalid && d.invalid.length) {
                    invalid = '<ul style="margin:6px 0 0 16px;">' +
                        d.invalid.map(function (s) { return '<li>' + escHtml(s) + '</li>'; }).join('') +
                        '</ul>';
                }
                showError('Verzending mislukt' + status + ': ' + msg + invalid);
                submitBtn.disabled = false;
            }
        })
        .catch(function (err) {
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
