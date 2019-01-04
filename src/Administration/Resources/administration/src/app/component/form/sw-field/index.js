import './sw-field.less';

/**
 * @private
 * @description sw-field as alias for all input fields
 * @status ready
 */
export default {
    name: 'sw-field',
    functional: true,

    render(createElement, context) {
        function getComponentName() {
            const components = {
                boolean: 'sw-boolean-field',
                checkbox: 'sw-checkbox-field',
                color: 'sw-colorpicker',
                date: 'sw-datepicker',
                datepicker: 'sw-datepicker',
                datetime: 'sw-datepicker',
                number: 'sw-number-field',
                password: 'sw-password-field',
                radio: 'sw-radio-field',
                select: 'sw-select-field',
                switch: 'sw-switch-field',
                textarea: 'sw-textarea-field',
                time: 'sw-datepicker'
            };

            return components[context.props.type] || 'sw-text-field';
        }

        Object.assign(context.data.attrs, context.props);

        return createElement(
            getComponentName(),
            context.data,
            context.children
        );
    },

    props: {
        type: {
            type: String,
            required: false,
            validator(value) {
                return ['text', 'select', 'boolean', 'checkbox', 'switch', 'number',
                    'radio', 'textarea', 'datetime', 'date', 'time', 'datepicker',
                    'colorpicker', 'confirm', 'password'].includes(value);
            }
        },
        suffix: {
            type: String,
            required: false
        }
    }
};
