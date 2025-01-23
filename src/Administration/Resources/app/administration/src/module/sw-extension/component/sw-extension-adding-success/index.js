import template from './sw-extension-adding-success.html.twig';
import './sw-extension-adding-success.scss';

/**
 * @sw-package checkout
 * @private
 */
export default {
    template,

    compatConfig: Shopware.compatConfig,

    emits: ['close'],
};
