import template from 'src/app/component/atom/sw-button/sw-button.html.twig';
import 'src/app/component/atom/sw-button/sw-button.less';

export default Shopware.Component.register('sw-button', {
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
