import template from './sw-cms-block-layout-config.html.twig';
import './sw-cms-block-layout-config.scss';
import { wrapComponentConfig } from '../dependencies';

/**
 * @private
 * @sw-package discovery
 */
export default wrapComponentConfig({
    template,

    props: {
        block: {
            type: Object as PropType<Entity<'cms_block'>>,
            required: true,
        },
    },
});
