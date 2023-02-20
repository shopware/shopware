import template from './sw-cms-visibility-config.html.twig';
import './sw-cms-visibility-config.scss';

/**
 * @private
 * @package content
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        visibility: {
            type: Object,
            required: true,
        },
    },
    methods: {
        onVisibilityChange(viewport: string, isVisible: boolean) {
            this.$emit('visibility-change', viewport, isVisible);
        },
    },
});
