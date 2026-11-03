import template from './sw-cms-stage-section-selection.html.twig';
import './sw-cms-stage-section-selection.scss';
import { wrapComponentConfig } from '../dependencies';

/**
 * @sw-package discovery
 *
 * @public
 */
export default wrapComponentConfig({
    template,

    emits: ['section-select'],

    methods: {
        selectSection(type: string) {
            this.$emit('section-select', type);
        },
    },
});
