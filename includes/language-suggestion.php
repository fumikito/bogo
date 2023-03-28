<?php

function bogo_language_suggestion( $args = '' ) {
	$args = wp_parse_args( $args, array(
		'echo' => false,
	) );

	$locale_to_suggest = false;

	foreach ( bogo_http_accept_languages() as $locale ) {
		$locale_to_suggest = bogo_get_closest_locale( $locale );

		if ( $locale_to_suggest ) {
			break;
		}
	}

	switch_to_locale( $locale_to_suggest );

	$output = sprintf(
		'This plugin is also available in %1$s.',
		bogo_get_language( $locale_to_suggest )
	);

	restore_previous_locale();

	$output = apply_filters( 'bogo_language_suggestion', $output, $args );

	if ( $args['echo'] ) {
		echo $output;
	} else {
		return $output;
	}
}