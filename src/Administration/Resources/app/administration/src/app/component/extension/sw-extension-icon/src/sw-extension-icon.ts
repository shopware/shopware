import template from './sw-extension-icon.html.twig';
import './sw-extension-icon.scss';

/**
 * @sw-package framework
 * @private
 */
export default {
    template,

    props: {
        src: {
            type: String,
            required: true,
        },
        alt: {
            type: String,
            required: false,
            default: '',
        },
    },
};
