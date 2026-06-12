import template from './sw-experience-studio-element-settings.html.twig';
import './sw-experience-studio-element-settings.scss';

/**
 * @private
 * @sw-package discovery
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        layout: {
            type: Object,
            required: false,
            default: null,
        },
        selectedElementId: {
            type: String,
            required: false,
            default: null,
        },
    },

    computed: {
        hasSelectedElement(): boolean {
            return this.selectedElementId !== null;
        },
    },
});
