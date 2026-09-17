<?php
/**
 * Plugin Name: Extend Entities: Default Layout
 * Description: Uses the Gutenberg view config API to make Grid the default layout for Pages, and to add custom fields to them.
 * Requires Plugins: gutenberg
 */

/*
 * The script module providing the JavaScript half of the `reading_time`
 * field (`getValue` and `render`). It is only registered here, not enqueued:
 * Gutenberg adds the module of every declared field to the import map and
 * the editor imports it on demand.
 */
add_action(
	'init',
	function () {
		wp_register_script_module(
			'extend-entities/reading-time-field',
			plugins_url( 'fields/reading-time.js', __FILE__ ),
			array(),
			filemtime( __DIR__ . '/fields/reading-time.js' )
		);
	}
);

add_filter(
	'get_entity_view_config_posttype_page',
	function ( $data ) {
		return $data->merge(
			array(
				'fields'       => array(
					// A field that is plain data: no JavaScript involved.
					array(
						'id'            => 'menu_order',
						'type'          => 'integer',
						'label'         => __( 'Order', 'extend-entities' ),
						'description'   => __( 'Position of the page among its siblings.', 'extend-entities' ),
						'enableSorting' => false,
						'filterBy'      => false,
					),
					// A field whose `getValue` and `render` come from a script module.
					array(
						'id'            => 'reading_time',
						'type'          => 'integer',
						'label'         => __( 'Reading time', 'extend-entities' ),
						'enableSorting' => false,
						'filterBy'      => false,
						'readOnly'      => true,
						'scriptModule'  => 'extend-entities/reading-time-field',
					),
				),
				// Show them in the list and in the Quick Edit form.
				'default_view' => array(
					'type'   => 'grid',
					'fields' => array( 'reading_time', 'menu_order' ),
				),
				'form'         => array(
					'fields' => array( 'menu_order', 'reading_time' ),
				),
			),
			1
		);
	}
);
