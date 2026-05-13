/* global jQuery, stadwagAjax */
(function ( $ ) {
    'use strict';

    var postId, nonce;

    function getBaseData() {
        postId = $( '#stadwag_post_id' ).val();
        nonce  = $( '#stadwag_nonce' ).val();
        return {
            post_id:     postId,
            nonce:       nonce,
            category_id: $( '#stadwag_category' ).val(),
            remarks:     $( '#stadwag_remarks' ).val()
        };
    }

    function setSpinner( active ) {
        $( '#stadwag-spinner' ).css( 'visibility', active ? 'visible' : 'hidden' );
    }

    function setFeedback( type, message ) {
        $( '#stadwag-feedback' )
            .removeClass( 'notice notice-success notice-error' )
            .addClass( type ? 'notice notice-' + type : '' )
            .html( message ? '<p>' + message + '</p>' : '' );
    }

    // -------------------------------------------------------------------
    // Stap 1: Voorvertoning ophalen
    // -------------------------------------------------------------------
    $( document ).on( 'click', '#stadwag-preview-btn', function () {
        var $btn  = $( this );
        var data  = getBaseData();
        data.action = 'stadwag_preview';

        $btn.prop( 'disabled', true );
        setSpinner( true );
        setFeedback( null, '' );
        $( '#stadwag-preview' ).hide();

        $.ajax( {
            url:    stadwagAjax.ajaxUrl,
            method: 'POST',
            data:   data,
            success: function ( response ) {
                if ( response.success ) {
                    $( '#stadwag-preview-content' ).html( response.data.html );
                    $( '#stadwag-preview' ).slideDown( 200 );
                } else {
                    var msg = ( response.data && response.data.message ) ? response.data.message : 'Onbekende fout.';
                    setFeedback( 'error', stadwagAjax.i18n.error + msg );
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

    // -------------------------------------------------------------------
    // Stap 2: Bevestigen en daadwerkelijk indienen
    // -------------------------------------------------------------------
    $( document ).on( 'click', '#stadwag-confirm-btn', function () {
        var $btn  = $( this );
        var $cancel = $( '#stadwag-cancel-btn' );
        var data  = getBaseData();
        data.action = 'stadwag_doorplaatsen';

        $btn.prop( 'disabled', true );
        $cancel.prop( 'disabled', true );
        $btn.text( stadwagAjax.i18n.sending );
        setSpinner( true );
        setFeedback( null, '' );

        $.ajax( {
            url:    stadwagAjax.ajaxUrl,
            method: 'POST',
            data:   data,
            success: function ( response ) {
                $( '#stadwag-preview' ).slideUp( 200 );

                if ( response.success ) {
                    setFeedback( 'success', response.data.message );

                    // Badge toevoegen als die er nog niet is
                    if ( response.data.forwarded_at && ! $( '.stadwag-forwarded-badge' ).length ) {
                        $( '#stadwag-preview-btn' ).closest( '.inside' ).prepend(
                            '<p class="stadwag-forwarded-badge" style="color:#46b450;font-weight:600;">' +
                            '&#10003; Doorgeplaatst op ' + response.data.forwarded_at +
                            '</p>'
                        );
                    }
                } else {
                    var msg = ( response.data && response.data.message ) ? response.data.message : 'Onbekende fout.';
                    setFeedback( 'error', stadwagAjax.i18n.error + msg );
                }
            },
            error: function ( xhr ) {
                $( '#stadwag-preview' ).slideUp( 200 );
                setFeedback( 'error', 'HTTP fout ' + xhr.status + '. Probeer het opnieuw.' );
            },
            complete: function () {
                $btn.prop( 'disabled', false ).text( 'Indienen bij Stad Wageningen' );
                $cancel.prop( 'disabled', false );
                setSpinner( false );
            }
        } );
    } );

    // -------------------------------------------------------------------
    // Annuleren: sluit voorvertoning
    // -------------------------------------------------------------------
    $( document ).on( 'click', '#stadwag-cancel-btn', function () {
        $( '#stadwag-preview' ).slideUp( 200 );
        setFeedback( null, '' );
    } );

}( jQuery ) );
