import 'src/app/component/base/sw-collapse';
import template from './sw-model-editor-collapse.html.twig';
import './sw-model-editor-collapse.scss';

/**
 * @sw-package discovery
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default Shopware.Component.wrapComponentConfig({
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
