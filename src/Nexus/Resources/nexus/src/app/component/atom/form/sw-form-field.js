import ComponentFactory from 'src/core/factory/component.factory';
import template from 'src/app/component/atom/form/sw-form-field/sw-form-field.html.twig';

export default ComponentFactory.register('sw-form-field', {
    props: {
        id: {
            type: String,
            required: true
        },
        type: {
            type: String,
            default: 'text'
        },
        name: {
            type: String,
            required: true
        },
        placeholder: {
            type: String,
            default: ''
        },
        value: {
            type: [String, Boolean],
            default: ''
        },
        label: {
            type: String,
            default: ''
        }
    },
    template
});
