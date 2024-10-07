/**
 * External dependencies.
 */
import { createRef, Component } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	isString,
	template,
	debounce
} from 'lodash';
import cx from 'classnames';

class RichTextField extends Component {
	/**
	 * Define the project base properties
	 *
	 * @return {void}
	 */
	constructor() {
		super();

		this.node = createRef();
		this.editor = null;
	}

	/**
	 * Lifecycle hook.
	 *
	 * @return {void}
	 */
	componentDidMount() {
		if ( this.props.visible ) {
			this.timer = setTimeout( this.initEditor, 250 );

			const resizeObserver = new ResizeObserver( debounce( () => {
				if ( this.editor ) {
					/**
					 * On each call of the `wpAutoResize` method the global `wpActiveEditor` reference
					 * is changed to the element that will be resized. In some cases this is causing
					 * conflicts with other plugins so we need to preserve and restore the previously
					 * referenced element.
					 */
					const activeEdtior = window.wpActiveEditor;
					this.editor.execCommand( 'wpAutoResize', undefined, undefined, { skip_focus: true } );
					window.wpActiveEditor = activeEdtior;
				}
			}, 100 ) );

			resizeObserver.observe( this.node.current );

			this.observer = resizeObserver;
		}
	}

	/**
	 * Lifecycle hook.
	 *
	 * @return {void}
	 */
	componentWillUnmount() {
		clearTimeout( this.timer );

		if ( typeof this.observer !== 'undefined' ) {
			this.observer.disconnect();
		}

		this.destroyEditor();
	}

	/**
	 * Handles the change of the input.
	 *
	 * @param  {Object|string} eventOrValue
	 * @return {void}
	 */
	handleChange = ( eventOrValue ) => {
		const { id, onChange } = this.props;

		onChange(
			id,
			isString( eventOrValue ) ? eventOrValue : eventOrValue.target.value
		);
	}

	/**
	 * Renders the component.
	 *
	 * @return {Object}
	 */
	render() {
		const {
			id,
			name,
			value,
			field
		} = this.props;

		const classes = [
			'carbon-wysiwyg',
			'wp-editor-wrap',
			{ 'tmce-active': field.rich_editing },
			{ 'html-active': ! field.rich_editing }
		];

		const mediaButtonsHTML = field.media_buttons
			? template( field.media_buttons )( { id } )
			: null;

		const shouldRenderTabs = field.rich_editing && window.tinyMCEPreInit.qtInit[ field.settings_reference ];

		return (
			<div
				id={ `wp-${ id }-wrap` }
				className={ cx( classes ) }
				ref={ this.node }
			>
				{ field.media_buttons && (
					<div id={ `wp-${ id }-media-buttons` } className="hide-if-no-js wp-media-buttons">
						<span dangerouslySetInnerHTML={ { __html: mediaButtonsHTML } }></span>
					</div>
				) }

				{ shouldRenderTabs && (
					<div className="wp-editor-tabs">
						<button type="button" id={ `${ id }-tmce` } className="wp-switch-editor switch-tmce" data-wp-editor-id={ id }>
							{ __( 'Visual', 'carbon-fields-ui' ) }
						</button>

						<button type="button" id={ `${ id }-html` } className="wp-switch-editor switch-html" data-wp-editor-id={ id }>
							{ __( 'Text', 'carbon-fields-ui' ) }
						</button>
					</div>
				) }

				<div id={ `wp-${ id }-editor-container` } className="wp-editor-container">
					<textarea
						style={ { width: '100%' } }
						className="regular-text"
						id={ id }
						name={ name }
						value={ value }
						onChange={ this.handleChange }
						{ ...field.attributes }
					/>
				</div>
			</div>
		);
	}

	/**
	 * Initialize the WYSIWYG editor.
	 *
	 * @return {void}
	 */
	initEditor = () => {
		const { id, field } = this.props;
		if ( field.rich_editing ) {
			const editorSetup = ( editor ) => {
				this.editor = editor;

				editor.on( 'blur Change', () => {
					editor.save();

					this.handleChange( editor.getContent() );
				} );
			};

			const editorOptions = {
				...window.tinyMCEPreInit.mceInit[ field.settings_reference ],
				selector: `#${ id }`,
				setup: editorSetup
			};

			window.tinymce.init( editorOptions );
		}

		const quickTagsOptions = { ...window.tinyMCEPreInit.qtInit[ field.settings_reference ] };

		if ( quickTagsOptions.length ) {
			const qtagInstance = window.quicktags( {
				...quickTagsOptions,
				id
			} );

			// Force the initialization of the quick tags.
			window.QTags._buttonsInit( qtagInstance.id );
		}
	}

	/**
	 * Destroy the instance of the WYSIWYG editor.
	 *
	 * @return {void}
	 */
	destroyEditor() {
		if ( this.editor ) {
			this.editor.remove();

			this.node = null;
			this.editor = null;
		}

		if (typeof window.QTags !== 'undefined' && window.QTags.instances[ this.props.id ]) {
			delete window.QTags.instances[ this.props.id ];
		}
	}
}

export default RichTextField;
