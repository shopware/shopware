import './sw-button.less';
import template from './sw-button.html.twig';

Shopware.Component.register('sw-button', {
    props: {
        isPrimary: {
            type: Boolean,
            required: false,
            default: false
        },
        isDisabled: {
            type: Boolean,
            required: false,
            default: false
        },
        link: {
            type: String,
            required: false,
            default: ''
        }
    },
    template
});
