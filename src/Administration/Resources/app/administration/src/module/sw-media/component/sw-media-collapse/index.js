import 'src/app/component/base/sw-collapse';
import template from './sw-media-collapse.html.twig';
import './sw-media-collapse.scss';

/**
 * @package content
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
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
