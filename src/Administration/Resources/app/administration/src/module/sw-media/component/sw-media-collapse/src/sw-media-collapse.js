import template from './sw-media-collapse.html.twig';
import './sw-media-collapse.scss';

/**
 * @sw-package discovery
 *
 * @public
 */
export default {
    template,

    props: {
        title: {
            type: String,
            required: true,
        },
    },

    computed: {
        expandButtonClass() {
            return {
                'is--hidden': this.expanded,
            };
        },
        collapseButtonClass() {
            return {
                'is--hidden': !this.expanded,
            };
        },
    },
};
