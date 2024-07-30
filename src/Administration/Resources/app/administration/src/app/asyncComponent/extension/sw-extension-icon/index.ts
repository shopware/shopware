import template from './sw-extension-icon.html.twig';
import './sw-extension-icon.scss';

/**
 * @package services-settings
 * @private
 */
export default {
    template,

    compatConfig: Shopware.compatConfig,

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
