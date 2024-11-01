JQuery(function($) {
    let xpresspay_submit = false;

    $('#wc-xpresspay-form').hide();

    wcXpressPayFormHandler();

    jQuery('#xpresspay-payment-button').click( function() {
		return wcXpressPayFormHandler();
	});

	jQuery('#xpresspay_form form#order_review').submit( function() {
		return wcXpressPayFormHandler();
	});





    function wcXpressPayFormHandler(){
        
        $('#wc-xpresspay-form').hide();

		if (xpresspay_submit) {
			xpresspay_submit = false;
			return true;
		}


		let $form = $( 'form#payment-form, form#order_review' ),
        xpresspay_txnref = $form.find('input.xpresspay_txnref');

        xpresspay_txnref.val('');

        let amount = wc_xpresspay_params.amount;

        let xpresspay_callback = function( response ) {
			$form.append( '<input type="hidden" class="xpresspay_txnref" name="xpresspay_txnref" value="' + response.trxref + '"/>' );
			paystack_submit = true;

			$form.submit();

			$( 'body' ).block( {
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				},
				css: {
					cursor: "wait"
				}
			} );
		};
    }

})