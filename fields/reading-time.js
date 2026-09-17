/**
 * JavaScript half of the fields this plugin registers for Pages.
 *
 * The default export maps field ids to a partial DataViews field: only what
 * PHP cannot serialize. Everything else (id, type, label, …) is declared on
 * the server and takes precedence over what is exported here.
 *
 * No build step: script modules cannot import the `@wordpress/*` scripts,
 * so the classic `wp.*` globals the editor already loaded are used instead.
 *
 * The class names emitted by `render` are styled by `reading-time.css`,
 * enqueued from PHP: a script module has no way to declare a stylesheet.
 */
const { createElement } = window.wp.element;
const { __, _n, sprintf } = window.wp.i18n;

const WORDS_PER_MINUTE = 200;
const LONG_READ_MINUTES = 10;

const readingTime = {
	getValue: ( { item } ) => {
		const content =
			typeof item.content === 'string'
				? item.content
				: item.content?.raw ?? item.content?.rendered ?? '';
		const words = content
			.replace( /<[^>]*>/g, ' ' )
			.split( /\s+/ )
			.filter( Boolean ).length;
		return Math.ceil( words / WORDS_PER_MINUTE );
	},
	render: ( { item, field } ) => {
		const minutes = field.getValue( { item } );
		const className = [
			'extend-entities-reading-time',
			minutes === 0 && 'is-empty',
			minutes >= LONG_READ_MINUTES && 'is-long',
		]
			.filter( Boolean )
			.join( ' ' );
		return createElement(
			'span',
			{ className },
			minutes === 0
				? __( 'Empty', 'extend-entities' )
				: sprintf(
						/* translators: %d: number of minutes. */
						_n( '%d min', '%d min', minutes, 'extend-entities' ),
						minutes
				  )
		);
	},
};

export default {
	reading_time: readingTime,
};
