/**
 * @sw-package framework
 */
import { MtHelpText } from '@shopware-ag/meteor-component-library';
import template from './sw-settings-services-gmv-info.html.twig';

/**
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    name: 'sw-settings-services-gmv-info',

    template,

    components: {
        MtHelpText,
    },
});
