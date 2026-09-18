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
 * provided by a script module. The module is registered with
 * `wp_register_script_module()`, where its URL, dependencies, and version
 * are declared, and `gutenberg_register_fields()` takes its id. It is not
 * enqueued: Gutenberg adds the modules of the registered fields to the
 * import map of the editor pages, where the editor imports them on demand.
 */
add_action(
	'init',
	function () {
		wp_register_script_module(
			'extend-entities/page-fields',
			plugins_url( 'fields/reading-time.js', __FILE__ ),
			array(),
			filemtime( __DIR__ . '/fields/reading-time.js' )
		);
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
			'extend-entities/page-fields'
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

/*
 * The CSS half of the fields.
 *
 * A script module cannot declare a stylesheet, so the CSS is a classic
 * style handle registered on `init` and enqueued wherever the fields can be
 * displayed. That is a different hook per screen:
 *
 * - `enqueue_block_editor_assets` covers the post editor (post.php) and the
 *   classic site editor (site-editor.php).
 * - `site-editor-v2_init` covers the extensible site editor: it renders its
 *   own document and exits on `admin_init`, before `admin_enqueue_scripts`
 *   and `enqueue_block_editor_assets` fire, so a style must be enqueued from
 *   its `{page}_init` action to be printed.
 * - `site-editor-v2-wp-admin_init` is the same page when it renders inside
 *   the wp-admin chrome.
 *
 * The stylesheet is printed on every load of those screens, whether or not
 * a view showing the fields is ever opened: nothing ties it to the field or
 * to the entity, unlike the script module, which the editor imports on
 * demand.
 */
add_action(
	'init',
	function () {
		wp_register_style(
			'extend-entities-fields',
			plugins_url( 'fields/reading-time.css', __FILE__ ),
			array(),
			filemtime( __DIR__ . '/fields/reading-time.css' )
		);
	}
);

function extend_entities_enqueue_field_styles() {
	wp_enqueue_style( 'extend-entities-fields' );
}
add_action( 'enqueue_block_editor_assets', 'extend_entities_enqueue_field_styles' );
add_action( 'site-editor-v2_init', 'extend_entities_enqueue_field_styles' );
add_action( 'site-editor-v2-wp-admin_init', 'extend_entities_enqueue_field_styles' );
