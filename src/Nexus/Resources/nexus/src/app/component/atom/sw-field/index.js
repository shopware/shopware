import './sw-field.less';
import template from './sw-field.html.twig';

export default Shopware.ComponentFactory.register('sw-field', {
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
            type: [String, Boolean, Number, Date],
            default: ''
        },
        suffix: {
            type: String,
            default: '',
            required: false
        },
        label: {
            type: String,
            default: ''
        },
        options: {
            type: Array,
            default: () => {
                return [];
            },
            required: false
        },
        isCurrency: {
            type: Boolean,
            default: false,
            required: false
        }
    },
    template
});
