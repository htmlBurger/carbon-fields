/**
 * The external dependencies.
 */
import React from 'react';
import PropTypes from 'prop-types';
import {
	compose,
	withHandlers,
	branch,
	renderComponent,
	setStatic,
	lifecycle,
	withState
} from 'recompose';

/**
 * The internal dependencies.
 */
import Field from 'fields/components/field';
import NoOptions from 'fields/components/no-options';
import withStore from 'fields/decorators/with-store';
import withSetup from 'fields/decorators/with-setup';
import { TYPE_SELECT, TYPE_GRAVITY_FORM, VALIDATION_BASE } from 'fields/constants';

import ClassicSelect from './ClassicSelect';
import StyledSelect from './StyledSelect';

/**
 * Render a select input field.
 *
 * @param  {Object}   props
 * @param  {Object}   props.name
 * @param  {Object}   props.field
 * @param  {Function} props.setFieldValue
 * @return {React.Element}
 */
export const SelectField = ({
	name,
	field,
	setFieldValue
}) => {
	let args = {
		name,
		field,
		setFieldValue
	}

	return <Field field={field}>
		{ field.has_ui ? <StyledSelect { ...args } /> : <ClassicSelect { ...args } /> }
	</Field>;
};

/**
 * Validate the props.
 *
 * @type {Object}
 */
SelectField.propTypes = {
	name: PropTypes.string,
	field: PropTypes.shape({
		id: PropTypes.string,
		value: PropTypes.string,
		options: PropTypes.arrayOf(PropTypes.shape({
			value: PropTypes.string,
			label: PropTypes.string,
		})),
	})
};

/**
 * The enhancer.
 *
 * @type {Function}
 */
export const enhance = compose(
	/**
	 * Connect to the Redux store.
	 */
	withStore(),

	/**
	 * Render "No-Options" component when the field doesn't have options.
	 */
	branch(
		/**
		 * Test to see if the "No-Options" should be rendered.
		 */
		({ field: { options } }) => options && options.length,

		/**
		 * Render the actual field.
		 */
		compose(
			/**
			 * Attach the setup hooks.
			 */
			withSetup({
				componentDidMount() {
					const {
						field,
						ui,
						setupField,
						setupValidation,
						setFieldValue,
					} = this.props;

					setupField(field.id, field.type, ui);

					// Supress validation errors when the fallback option has a falsy value.
					// An example is when the field is used to render 'Gravity Form' selectbox.
					if (field.required) {
						setupValidation(field.id, VALIDATION_BASE);
					}
				}
			}),

			/**
			 * Pass some handlers to the component.
			 */
			withHandlers()
		),

		/**
		 * Render the empty component.
		 */
		renderComponent(NoOptions)
	)
);

export default setStatic('type', [
	TYPE_SELECT,
	TYPE_GRAVITY_FORM,
])(enhance(SelectField));
