/**
 * @sw-package framework
 */

import template from './sw-settings-services-framed-icon.html.twig';
import './sw-settings-services-framed-icon.scss';

/**
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    name: 'sw-settings-services-framed-icon',
    template,

    props: {
        imageUrl: {
            type: String,
            required: true,
        },
        size: {
            type: String,
            required: true,
        },
    },
});
