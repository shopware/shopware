import template from './sw-cms-stage-section-selection.html.twig';
import './sw-cms-stage-section-selection.scss';

/**
 * @package buyers-experience
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default Shopware.Component.wrapComponentConfig({
    template,

    compatConfig: Shopware.compatConfig,

    emits: ['section-select'],

    methods: {
        selectSection(type: string) {
            this.$emit('section-select', type);
        },
    },
});
