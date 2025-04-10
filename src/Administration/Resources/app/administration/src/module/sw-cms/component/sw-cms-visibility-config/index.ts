import { type PropType } from 'vue';
import template from './sw-cms-visibility-config.html.twig';
import './sw-cms-visibility-config.scss';
import type CmsVisibility from '../../shared/CmsVisibility';

/**
 * @private
 * @sw-package discovery
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        visibility: {
            type: Object as PropType<CmsVisibility>,
            required: true,
        },
    },
    methods: {
        onVisibilityChange(viewport: string, isVisible: boolean) {
            this.$emit('visibility-change', viewport, isVisible);
        },
    },
});
