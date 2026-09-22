<?php
/**
 * Plugin Name: Extend Entities
 * Description: Demonstrates the Gutenberg APIs to extend an entity (Pages): the view config filter, declarative fields, fields backed by JavaScript and CSS, and patching or substituting an existing field.
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
 * current config: a scalar replaces the current value and a list merges into
 * the current list by member identity, so `default_view.fields` here appends
 * to the fields the default view already shows (use `replace()` to pin a list
 * to an exact set of members). The second argument is the schema version the
 * patch is written against, currently `1`.
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
 * (see `fields/page-fields.js`).
 *
 * The module is registered with `wp_register_script_module()`, where its
 * URL, dependencies, and version are declared, and its id is passed as the
 * fourth argument of `gutenberg_register_fields()`. It is not enqueued:
 * Gutenberg adds the modules of the registered fields to the import map of
 * the editor pages, where the editor imports them on demand.
 *
 * Calling `gutenberg_register_fields()` again for the same entity adds to
 * the fields registered in case 2; a field with a new id is appended and a
 * field with the id of an existing one patches it (see case 5).
 */
add_action(
	'init',
	function () {
		wp_register_script_module(
			'extend-entities/page-fields',
			plugins_url( 'fields/page-fields.js', __FILE__ ),
			array(),
			filemtime( __DIR__ . '/fields/page-fields.js' )
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
 * The CSS half of the field (see `fields/page-fields.css`).
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
			plugins_url( 'fields/page-fields.css', __FILE__ ),
			array(),
			filemtime( __DIR__ . '/fields/page-fields.css' )
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
 * =========================================================================
 * Case 4: patch an existing field by registering it.
 * =========================================================================
 *
 * `gutenberg_register_fields()` with the id of an existing field patches it
 * too: only `id` is required, the properties given are merged into the
 * field, property by property, and it keeps its position. A property given
 * replaces the current one wholesale, so this is how to swap `elements`.
 *
 * Unlike the filter, a registration can come with a script module, and the
 * module applies to the field on top of the modules it already has, so the
 * JavaScript half is patched the same way, export by export. Below, the
 * `comment_status` field of Pages becomes sortable and gets a custom
 * `render` from `fields/page-fields.js` (the module of case 3, applying
 * to one more field): a colored dot next to the label of the current
 * option. Everything else stays: the `elements` and the `radio` control of
 * the core definition, and the relabel of case 4, which runs after this.
 *
 * To swap a control instead, export an `Edit` component for the field; to
 * change how a core field such as `author` renders while keeping its
 * `getElements` and `setValue`, export a `render` for it. Nothing can be
 * removed this way: see case 6 for that.
 */
add_action(
	'init',
	function () {
		gutenberg_register_fields(
			'postType',
			'page',
			array(
				array(
					'id'            => 'comment_status',
					'enableSorting' => true,
				),
			),
			'extend-entities/page-fields'
		);
	}
);

/*
 * =========================================================================
 * Case 5: substitute an existing field.
 * =========================================================================
 *
 * Patching (case 4) can only add or replace properties: neither a
 * server property nor a JavaScript export can be taken away, since the
 * editor layers every module listed for the field. To start from scratch,
 * unregister the field first: `gutenberg_unregister_fields()` drops its
 * definition, default or registered, and detaches every module from it,
 * the core one included.
 *
 * The registration that follows is then the whole field. Every property it
 * should have must be declared, and the only JavaScript that applies is the
 * module passed along, if any. Below, `author` becomes a plain integer:
 * without the core `render` and `getElements`, the column shows the user id
 * the record holds and the Quick Edit form offers a number control. To
 * render it differently, register a module exporting an `author` entry with
 * the `render` (and `Edit`, `getElements`, …) it needs.
 *
 * The fields of an entity are keyed by id, default fields first and
 * registered ones after, so the substitute of a default field moves to the
 * end of the list: it is a registered field now. Since another plugin may
 * patch or substitute the same field, the priority of the `init` callbacks
 * decides who has the last word; the substitution runs late for that.
 */
add_action(
	'init',
	function () {
		gutenberg_unregister_fields( 'postType', 'page', array( 'author' ) );
		gutenberg_register_fields(
			'postType',
			'page',
			array(
				array(
					'id'       => 'author',
					'type'     => 'integer',
					'label'    => __( 'Written by', 'extend-entities' ),
					'filterBy' => false,
				),
			)
		);
	},
	20
);

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
