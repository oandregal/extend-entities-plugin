<?php
/**
 * Plugin Name: Extend Entities: Default Layout
 * Description: Uses the Gutenberg view config API to make Grid the default layout for Pages, and to add custom fields to them.
 * Requires Plugins: gutenberg
 */

/*
 * The fields added to Pages. Registering them is all it takes for them to
 * reach the view config endpoint and the editor.
 *
 * The `reading_time` field has a JavaScript half (`getValue` and `render`)
 * provided by a script module registered for the entity along with the
 * fields: `gutenberg_register_fields()` takes its id and URL and registers
 * it. It is not enqueued: Gutenberg adds the modules of the registered
 * fields to the import map of the editor pages, where the editor imports
 * them on demand.
 */
add_action(
	'init',
	function () {
		gutenberg_register_fields(
			'postType',
			'page',
			array(
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
				),
			),
			'extend-entities/reading-time-field',
			plugins_url( 'fields/reading-time.js', __FILE__ )
		);
	}
);

/*
 * The view config filter still shapes how Pages are shown: the default
 * layout and which fields the list and the Quick Edit form display.
 */
add_filter(
	'get_entity_view_config_posttype_page',
	function ( $data ) {
		return $data->merge(
			array(
				// Show the registered fields in the list and in the Quick Edit form.
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
