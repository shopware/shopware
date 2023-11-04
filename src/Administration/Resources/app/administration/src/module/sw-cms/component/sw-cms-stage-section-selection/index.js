import template from './sw-cms-stage-section-selection.html.twig';
import './sw-cms-stage-section-selection.scss';

/**
 * @package content
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    methods: {
        selectSection(type) {
            this.$emit('section-select', type);
        },
    },
};
