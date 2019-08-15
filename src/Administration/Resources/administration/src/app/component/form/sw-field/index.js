const { Component } = Shopware;

/**
 * @private
 * @description sw-field as alias for all input fields
 * @example-type dynamic
 * @status ready
 */
Component.register('sw-field', {
    functional: true,

    watch: {
        type() {
            this.$forceUpdate();
        }
    },

    render(createElement, context) {
        function getComponentName() {
            const components = {
                checkbox: 'sw-checkbox-field',
                colorpicker: 'sw-colorpicker',
                compactColorpicker: 'sw-compact-colorpicker',
                date: 'sw-datepicker',
                number: 'sw-number-field',
                password: 'sw-password-field',
                radio: 'sw-radio-field',
                select: 'sw-select-field',
                switch: 'sw-switch-field',
                textarea: 'sw-textarea-field',
                url: 'sw-url-field'
            };
            return components[context.props.type] || 'sw-text-field';
        }

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
            default: 'text',
            validValues: [
                'text',
                'select',
                'checkbox',
                'switch',
                'number',
                'radio',
                'textarea',
                'date',
                'colorpicker',
                'compactColorpicker',
                'confirm',
                'password',
                'url'
            ],
            validator(value) {
                return [
                    'text',
                    'select',
                    'checkbox',
                    'switch',
                    'number',
                    'radio',
                    'textarea',
                    'date',
                    'colorpicker',
                    'compactColorpicker',
                    'confirm',
                    'password',
                    'url'
                ].includes(value);
            }
        }
    }
});
