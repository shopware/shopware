import template from './sw-settings-usage-data.html.twig';

/**
 * @private
 *
 * @sw-package framework
 */
export default Shopware.Component.wrapComponentConfig({
    name: 'sw-settings-usage-data',

    compatConfig: Shopware.compatConfig,

    template,
});
