jQuery(document).ready(function ($) {
	'use strict';

	$('.host-locally').on('click', function () {
		$(this).addClass('updating-message');
	});

	$('.get-support').on('click', function () {
		var url = new URL(
			'https://app.codeable.io/affiliate-form/affiliate-form.html'
		);
		var args = new URLSearchParams();

		args.set('origin', location.origin);
		args.set('affiliateUrl', location.href);
		args.set('shortcode', 'BmTD5');
		args.set('TB_iframe', 'true');
		args.set('height', 800);
		args.set('width', 900);

		tb_show(
			$(this).text(),
			'https://app.codeable.io/affiliate-form/affiliate-form.html?' +
				args.toString()
		);
	});
});
