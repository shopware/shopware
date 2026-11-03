import template from './sw-cms-visibility-config.html.twig';
import './sw-cms-visibility-config.scss';
import { type CmsVisibility, wrapComponentConfig } from '../dependencies';

/**
 * @private
 * @sw-package discovery
 */
export default wrapComponentConfig({
    template,

    emits: ['visibility-change'],

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
