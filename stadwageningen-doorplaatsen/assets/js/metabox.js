/* global jQuery, stadwagAjax */
(function ( $ ) {
    'use strict';

    function setSpinner( active ) {
        $( '#stadwag-spinner' ).css( 'visibility', active ? 'visible' : 'hidden' );
    }

    function setFeedback( type, message ) {
        $( '#stadwag-feedback' )
            .removeClass( 'notice notice-success notice-error' )
            .addClass( type ? 'notice notice-' + type : '' )
            .html( message ? '<p>' + message + '</p>' : '' );
    }

    $( document ).on( 'click', '#stadwag-queue-btn', function () {
        var $btn = $( this );

        $btn.prop( 'disabled', true );
        setSpinner( true );
        setFeedback( null, '' );

        $.ajax( {
            url:    stadwagAjax.ajaxUrl,
            method: 'POST',
            data: {
                action:      'stadwag_queue',
                nonce:       $( '#stadwag_nonce' ).val(),
                post_id:     $( '#stadwag_post_id' ).val(),
                category_id: $( '#stadwag_category' ).val(),
                caption:     $( '#stadwag_caption' ).val() || '',
                credit:      $( '#stadwag_credit' ).val()  || ''
            },
            success: function ( response ) {
                if ( response.success ) {
                    setFeedback( 'success', response.data.message );
                    $( '#stadwag-queued-badge' ).show();
                    // Open de tip-de-redactie-pagina in een nieuw tabblad.
                    // Het userscript draait daar automatisch en vult alles in.
                    var url = ( response.data.form_url ) ? response.data.form_url : stadwagAjax.formUrl;
                    window.open( url, '_blank' );
                } else {
                    var msg = ( response.data && response.data.message ) ? response.data.message : 'Onbekende fout.';
                    setFeedback( 'error', msg );
                }
            },
            error: function ( xhr ) {
                setFeedback( 'error', 'HTTP fout ' + xhr.status + '. Probeer het opnieuw.' );
            },
            complete: function () {
                $btn.prop( 'disabled', false );
                setSpinner( false );
            }
        } );
    } );

}( jQuery ) );
