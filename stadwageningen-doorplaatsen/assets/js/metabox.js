/* global jQuery, stadwagAjax */
(function ( $ ) {
    'use strict';

    $( document ).on( 'click', '#stadwag-submit-btn', function () {
        var $btn      = $( this );
        var $spinner  = $( '#stadwag-spinner' );
        var $feedback = $( '#stadwag-feedback' );
        var postId    = $( '#stadwag_post_id' ).val();
        var nonce     = $( '#stadwag_nonce' ).val();

        // UI: bezig-staat
        $btn.prop( 'disabled', true );
        $spinner.css( 'visibility', 'visible' );
        $feedback
            .removeClass( 'notice notice-success notice-error' )
            .html( '<em>' + stadwagAjax.i18n.sending + '</em>' );

        $.ajax( {
            url:    stadwagAjax.ajaxUrl,
            method: 'POST',
            data: {
                action:      'stadwag_doorplaatsen',
                nonce:       nonce,
                post_id:     postId,
                category_id: $( '#stadwag_category' ).val(),
                remarks:     $( '#stadwag_remarks' ).val()
            },
            success: function ( response ) {
                if ( response.success ) {
                    $feedback
                        .addClass( 'notice notice-success' )
                        .html( '<p>' + response.data.message + '</p>' );

                    // Badge toevoegen als die er nog niet is
                    if ( response.data.forwarded_at && ! $( '.stadwag-forwarded-badge' ).length ) {
                        $btn.closest( '.inside' ).prepend(
                            '<p class="stadwag-forwarded-badge" style="color:#46b450;font-weight:600;">' +
                            '&#10003; Doorgeplaatst op ' + response.data.forwarded_at +
                            '</p>'
                        );
                    }
                } else {
                    var msg = ( response.data && response.data.message )
                        ? response.data.message
                        : 'Onbekende fout.';
                    $feedback
                        .addClass( 'notice notice-error' )
                        .html( '<p>' + stadwagAjax.i18n.error + msg + '</p>' );
                }
            },
            error: function ( xhr ) {
                $feedback
                    .addClass( 'notice notice-error' )
                    .html( '<p>HTTP fout ' + xhr.status + '. Probeer het opnieuw.</p>' );
            },
            complete: function () {
                $btn.prop( 'disabled', false );
                $spinner.css( 'visibility', 'hidden' );
            }
        } );
    } );

}( jQuery ) );
