<?php
/**
 * Plugin Name: Extend Entities
 * Description: Demonstrates the Gutenberg APIs to extend an entity (Pages): the view config filter, declarative fields, fields backed by JavaScript and CSS, and updating an existing field by patching or re-registering it.
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
 * =========================================================================
 * Case 4: update an existing field.
 * =========================================================================
 *
 * The fields of an entity live under the `fields` key of its view config,
 * so the same filter as in case 1 can change one that already exists: the
 * default fields Gutenberg derives from the post type `supports`
 * (`author`, `comment_status`, …) and the fields other plugins registered.
 *
 * `merge()` matches a list member by its `id` and merges the given
 * properties into it in place: the field keeps its position and every
 * property that is not named, including the JavaScript half it gets from
 * its script module (here the `render` and `Edit` of the core field).
 *
 * This example relabels the `comment_status` field of Pages and lets users
 * hide it from the list, which the core definition does not allow.
 *
 * A property whose value is a list of maps without an `id`, such as
 * `elements`, cannot be patched member by member: `merge()` appends to it.
 * Use `replace()` to pin such a list to an exact set of members, or
 * re-register the whole field.
 *
 * The alternative is calling `gutenberg_register_fields()` with the id of
 * the existing field: the new definition replaces the old one wholesale, in
 * the same position, so every property must be repeated, but it can also
 * come with a script module that provides a new `render`. Since the
 * properties declared on the server take precedence over the ones from the
 * modules, and the modules of every registration are applied to every field
 * by id, a re-registered core field still gets its JavaScript from core.
 */
add_filter(
	'get_entity_view_config_posttype_page',
	function ( $data ) {
		return $data->merge(
			array(
				'fields' => array(
					array(
						'id'           => 'comment_status',
						'label'        => __( 'Comments', 'extend-entities' ),
						'description'  => __( 'Whether visitors can comment on the page.', 'extend-entities' ),
						'enableHiding' => true,
					),
				),
			),
			1
		);
	}
);

/*
 * =========================================================================
 * Case 5: replace an existing field by re-registering it.
 * =========================================================================
 *
 * The alternative to the filter of case 4: `gutenberg_register_fields()`
 * with the id of an existing field. The fields of an entity are keyed by
 * id, default fields first and registered ones after, so a registered field
 * with the id of a default one takes its place, keeping its position. The
 * same goes for a field another plugin registered: the last registration
 * wins, so the priority of the `init` callback decides who does.
 *
 * Unlike the filter, the new definition replaces the old one wholesale, so
 * every server-side property the field should keep must be repeated: a
 * `type`, `Edit`, or `elements` left out is gone. Below, the core
 * `filterBy` operators are dropped by declaring `filterBy => false`.
 *
 * The JavaScript half of a field is a different matter, since it never
 * lives in the server definition. The editor imports every script module
 * of the entity, the one of the core fields and the ones registered by
 * plugins, and combines their exports field by field and property by
 * property, the later modules winning; the server definition is then
 * applied on top. So re-registering a field on its own does not touch its
 * JavaScript, and leaving a property out of the definition cannot remove
 * it: the core `render`, `getElements`, and `setValue` of `author` still
 * apply below, so the column shows the author name, not the integer the
 * record holds. To change a JavaScript property, export it for the field id
 * from a module registered along with the field, e.g. an `author` entry with
 * a `render` in `fields/reading-time.js` would replace the core one. This is
 * what the filter cannot do, as it cannot register a module.
 *
 * Relabeling the field and dropping its filter could also have been done
 * with the filter of case 4, since both are plain properties. The
 * difference shows in how much has to be spelled out.
 */
add_action(
	'init',
	function () {
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
