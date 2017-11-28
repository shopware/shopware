import './sw-field.less';
import template from './sw-field.html.twig';

export default Shopware.Component.register('sw-field', {
    props: {
        type: {
            type: String,
            default: 'text'
        },
        id: {
            type: String,
            required: false
        },
        name: {
            type: String,
            required: false
        },
        placeholder: {
            type: String,
            default: '',
            required: false
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
