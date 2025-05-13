import './sw-shopware-updates-info.scss';
import template from './sw-shopware-updates-info.html.twig';

const { Component } = Shopware;

/**
 * @sw-package framework
 * @private
 */
export default Component.wrapComponentConfig({
    template,

    props: {
        changelog: {
            type: String,
            required: true,
        },
        isLoading: {
            type: Boolean,
        },
    },
});
