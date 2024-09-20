import { type PropType } from 'vue';
import template from './sw-cms-block-layout-config.html.twig';
import './sw-cms-block-layout-config.scss';

/**
 * @private
 * @package buyers-experience
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    compatConfig: Shopware.compatConfig,

    props: {
        block: {
            type: Object as PropType<EntitySchema.Entity<'cms_block'>>,
            required: true,
        },
    },
});
