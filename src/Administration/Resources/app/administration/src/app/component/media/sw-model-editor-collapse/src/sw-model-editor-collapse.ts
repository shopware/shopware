import template from './sw-model-editor-collapse.html.twig';
import './sw-model-editor-collapse.scss';
import { wrapComponentConfig } from '../dependencies';

/**
 * @sw-package discovery
 *
 * @private
 */
export default wrapComponentConfig({
    template,

    props: {
        title: {
            type: String,
            required: true,
        },
    },

    computed: {
        expandButtonClass(): { 'is--hidden': boolean } {
            return {
                'is--hidden': (this as unknown as { expanded: boolean }).expanded,
            };
        },
        collapseButtonClass(): { 'is--hidden': boolean } {
            return {
                'is--hidden': !(this as unknown as { expanded: boolean }).expanded,
            };
        },
    },
});
