<?php
/**
 * Plugin Name: Extend Entities: Default Layout
 * Description: Uses the Gutenberg view config API to make Grid the default layout for Pages.
 * Requires Plugins: gutenberg
 */

add_filter(
	'get_entity_view_config_posttype_page',
	function ( $data ) {
		return $data->merge( array( 'default_view' => array( 'type' => 'grid' ) ), 1 );
	}
);
