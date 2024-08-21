/**
 * External dependencies.
 */
import cx from 'classnames';
import { Component } from '@wordpress/element';
import {
	map,
	find,
	kebabCase,
	isPlainObject
} from 'lodash';

/**
 * Carbon Fields dependencies.
 */
import { getFieldType } from '@carbon-fields/core';

/**
 * Internal dependencies.
 */
import './style.scss';
import Field from '../field';

class Container extends Component {
	/**
	 * Local state.
	 *
	 * @type {Object}
	 */
	state = {
		currentTab: null
	};

	/**
	 * Lifecycle hook.
	 *
	 * @return {void}
	 */
	componentDidMount() {
		const { container } = this.props;

		if ( this.isTabbed( container ) ) {
			this.setState( {
				currentTab: Object.keys( container.settings.tabs )[ 0 ]
			} );
		}
	}

	/**
	 * Returns whether the container uses tabs.
	 *
	 * @param  {Object} container
	 * @return {boolean}
	 */
	isTabbed( container ) {
		return isPlainObject( container.settings.tabs );
	}

	/**
	 * Renders the given field.
	 *
	 * @param  {Object} field
	 * @return {Object}
	 */
	renderField = ( field ) => {
		const FieldEdit = getFieldType( field.type, 'metabox' );

		if ( ! FieldEdit ) {
			return null;
		}

		return (
			<Field key={ field.id } id={ field.id }>
				<FieldEdit id={ field.id } containerId={ this.props.id } />
			</Field>
		);
	}

	/**
	 * Handles click on the tabs.
	 *
	 * @param  {string} tab
	 * @return {void}
	 */
	handleTabClick = ( tab ) => {
		this.setState( {
			currentTab: tab
		} );
	}

	/**
	 * Renders the component.
	 *
	 * @return {Object}
	 */
	render() {
		const { currentTab } = this.state;
		const { container } = this.props;

		const hasTabs = this.isTabbed( container );

		const classes = cx( [
			'cf-container',
			`cf-container-${ container.id }`,
			`cf-container-${ kebabCase( container.type ) }`,
			...container.classes,
			{
				'cf-container--plain': ! hasTabs,
				[ `cf-container--tabbed cf-container--${ container.layout }` ]: hasTabs
			}
		] );
		return (
			<div className={ classes }>
				<input
					type="hidden"
					name={ container.nonce.name }
					value={ container.nonce.value }
				/>

				{ hasTabs && (
					<div className={ `cf-container__tabs cf-container__tabs--${ container.layout }` }>
						<ul className="cf-container__tabs-list">
							{ map( container.settings.tabs, ( fieldNames, tabName ) => {
								// eslint-disable-next-line no-shadow
								const classes = cx(
									'cf-container__tabs-item',
									{
										'cf-container__tabs-item--current': tabName === currentTab
									}
								);

								return (
									<li
										key={ tabName }
										className={ classes }
										tabIndex={ -1 }
										role="tab"
										aria-selected={ currentTab === tabName }
									>
										<button
											type="button"
											onClick={ () => this.handleTabClick( tabName ) }
											dangerouslySetInnerHTML={ { __html: tabName } }
										/>
									</li>
								);
							} ) }
						</ul>
					</div>
				) }

				{ hasTabs && (
					map( container.settings.tabs, ( fieldNames, tabName ) => {
						return (
							<div className="cf-container__fields" key={ tabName } hidden={ tabName !== currentTab }>
								{ map( fieldNames, ( fieldName ) => {
									const field = find( container.fields, [ 'name', fieldName ] );
									return this.renderField( field );
								} ) }
							</div>
						);
					} )
				) }

				{ ! hasTabs && (
					<div className="cf-container__fields">
						{ map( container.fields, this.renderField ) }
					</div>
				) }
			</div>
		);
	}
}

export default Container;
