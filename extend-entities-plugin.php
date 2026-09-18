<?php
/**
 * Plugin Name: Extend Entities
 * Description: Demonstrates the Gutenberg APIs to extend an entity (Pages): the view config filter, declarative fields, and fields backed by JavaScript and CSS.
 * Requires Plugins: gutenberg
 */

/*
 * =========================================================================
 * Case 1: extend the view config.
 * =========================================================================
 *
 * The view config shapes how an entity is shown: the default layout of the
 * list, which fields it displays, and which fields the Quick Edit form
 * shows. It is exposed on a REST endpoint and consumed by the editor.
 *
 * The `get_entity_view_config_{kind}_{name}` filter receives a config
 * object whose `merge()` method deep-merges the given array into the
 * current config. The second argument is the depth of the merge: here `1`,
 * so `default_view.fields` replaces the previous list instead of being
 * appended to it.
 *
 * This example makes Grid the default layout for Pages, and shows the two
 * fields registered below in the list and in the Quick Edit form. Without
 * this filter the fields still exist (they can be added from the view
 * options), they are just not shown by default.
 */
add_filter(
	'get_entity_view_config_posttype_page',
	function ( $data ) {
		return $data->merge(
			array(
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
 * =========================================================================
 * Case 2: add a declarative field (no JavaScript, no CSS).
 * =========================================================================
 *
 * A field whose value is a plain property of the entity record needs no
 * client code. `gutenberg_register_fields()` takes a field definition in
 * the DataViews field shape; the editor reads the value from the record
 * property named by `id` and renders it according to `type`.
 *
 * Registering the field is all it takes for it to reach the view config
 * endpoint and the editor.
 */
add_action(
	'init',
	function () {
		gutenberg_register_fields(
			'postType',
			'page',
			array(
				array(
					'id'            => 'menu_order',
					'type'          => 'integer',
					'label'         => __( 'Order', 'extend-entities' ),
					'description'   => __( 'Position of the page among its siblings.', 'extend-entities' ),
					'enableSorting' => false,
					'filterBy'      => false,
				),
			)
		);
	}
);

/*
 * =========================================================================
 * Case 3: add a field that requires JavaScript and CSS.
 * =========================================================================
 *
 * A field whose value is computed, or whose rendering is custom, has a
 * JavaScript half: `getValue` and `render`, provided by a script module
 * (see `fields/reading-time.js`).
 *
 * The module is registered with `wp_register_script_module()`, where its
 * URL, dependencies, and version are declared, and its id is passed as the
 * fourth argument of `gutenberg_register_fields()`. It is not enqueued:
 * Gutenberg adds the modules of the registered fields to the import map of
 * the editor pages, where the editor imports them on demand.
 *
 * Calling `gutenberg_register_fields()` again for the same entity adds to
 * the fields registered in case 2; it does not replace them.
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
 * The CSS half of the field (see `fields/reading-time.css`).
 *
 * A script module cannot declare a stylesheet, so the CSS is a classic
 * style handle registered on `init` and enqueued wherever the field can be
 * displayed. That is a different hook per screen:
 *
 * - `enqueue_block_editor_assets` covers the post editor (post.php) and the
 *   classic site editor (site-editor.php).
 * - `site-editor-v2_init` covers the extensible site editor: it renders its
 *   own document and exits on `admin_init`, before `admin_enqueue_scripts`
 *   and `enqueue_block_editor_assets` fire, so a style must be enqueued from
 *   its `{page}_init` action to be printed.
 * - `site-editor-v2-wp-admin_init` is the same page when it renders inside
 *   the wp-admin chrome (see the submenu registered below).
 *
 * The stylesheet is printed on every load of those screens, whether or not
 * a view showing the field is ever opened: nothing ties it to the field or
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
// Post editor and site editor.
add_action( 'enqueue_block_editor_assets', 'extend_entities_enqueue_field_styles' );
// Site editor v2.
add_action( 'site-editor-v2_init', 'extend_entities_enqueue_field_styles' );
// Extensible site editor inside the wp-admin chrome.
add_action( 'site-editor-v2-wp-admin_init', 'extend_entities_enqueue_field_styles' );

/*
 * Supporting code for the last hook above: a submenu that renders the
 * extensible site editor inside the wp-admin chrome, opening on the Pages
 * list when no path is given. It exists only so the
 * `site-editor-v2-wp-admin_init` case can be exercised.
 */
add_action(
	'admin_menu',
	function () {
		$hook_suffix = add_submenu_page(
			'themes.php',
			__( 'Pages (embedded)', 'extend-entities' ),
			__( 'Pages (embedded)', 'extend-entities' ),
			'edit_theme_options',
			'site-editor-v2-wp-admin',
			'gutenberg_site_editor_v2_wp_admin_render_page'
		);

		if ( $hook_suffix ) {
			add_action(
				"load-$hook_suffix",
				function () {
					// phpcs:ignore WordPress.Security.NonceVerification.Recommended
					if ( empty( $_GET['p'] ) ) {
						wp_safe_redirect( admin_url( 'admin.php?page=site-editor-v2-wp-admin&p=' . rawurlencode( '/types/page/list/all' ) ) );
						exit;
					}
				}
			);
		}
	}
);
