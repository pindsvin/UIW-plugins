jQuery(document).ready(function ($) {
    var previewBtn  = $('#cultuur-wageningen-preview-btn');
    var submitWrap  = $('#cultuur-wageningen-submit-wrap');
    var submitBtn   = $('#cultuur-wageningen-btn');
    var previewBox  = $('#cultuur-wageningen-preview');
    var status      = $('#cultuur-wageningen-status');
    var nonce       = $('#cultuur_wageningen_nonce').val();

    previewBtn.on('click', function () {
        var postId = previewBtn.data('post-id');

        previewBtn.prop('disabled', true).text('Gegevens ophalen…');
        status.html('');
        previewBox.hide();
        submitWrap.hide();

        $.ajax({
            url:  cultuurWageningen.ajaxUrl,
            type: 'POST',
            data: {
                action:  'cultuur_wageningen_preview',
                nonce:   nonce,
                post_id: postId,
            },
            success: function (response) {
                previewBtn.prop('disabled', false).text('Controleer voor verzending');

                if (!response.success) {
                    status.html('<p style="color:#c00;margin:4px 0 0;">' + escHtml(response.data.message) + '</p>');
                    return;
                }

                var d = response.data;
                previewBox.html(
                    '<strong>Naam:</strong> '        + escHtml(d.naam)        + '<br>' +
                    '<strong>E-mail:</strong> '      + escHtml(d.email)       + '<br>' +
                    '<strong>Titel:</strong> '       + escHtml(d.titel)       + '<br>' +
                    '<strong>Afbeelding:</strong> '  + escHtml(d.afbeelding)  + '<br>' +
                    '<strong>Bericht:</strong><br>'  + escHtml(d.bericht)
                ).show();

                submitWrap.show();
            },
            error: function () {
                previewBtn.prop('disabled', false).text('Controleer voor verzending');
                status.html('<p style="color:#c00;margin:4px 0 0;">Er is een onverwachte fout opgetreden.</p>');
            },
        });
    });

    submitBtn.on('click', function () {
        var postId = submitBtn.data('post-id');

        submitBtn.prop('disabled', true).text('Bezig met verzenden…');
        previewBtn.prop('disabled', true);
        status.html('');

        $.ajax({
            url:  cultuurWageningen.ajaxUrl,
            type: 'POST',
            data: {
                action:  'cultuur_wageningen_submit',
                nonce:   nonce,
                post_id: postId,
            },
            success: function (response) {
                previewBtn.prop('disabled', false);

                if (response.success) {
                    previewBox.hide();
                    submitWrap.hide();
                    status.html('<p style="color:#2a9000;margin:4px 0 0;">' + escHtml(response.data.message) + '</p>');
                    submitBtn.text('Bevestig en verstuur').prop('disabled', false);
                } else {
                    status.html('<p style="color:#c00;margin:4px 0 0;">' + escHtml(response.data.message) + '</p>');
                    submitBtn.text('Bevestig en verstuur').prop('disabled', false);
                }
            },
            error: function () {
                previewBtn.prop('disabled', false);
                status.html('<p style="color:#c00;margin:4px 0 0;">Er is een onverwachte fout opgetreden.</p>');
                submitBtn.text('Bevestig en verstuur').prop('disabled', false);
            },
        });
    });

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }
});
