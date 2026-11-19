import template from './sw-experience-studio-frame-menu.html.twig';
import './sw-experience-studio-frame-menu.scss';

/**
 * @private
 * @sw-package discovery
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        label: {
            type: String,
            required: true,
        },
    },

    emits: ['open-another', 'translate'],

    data() {
        return {
            popoverClass: `experience-studio-frame-menu-${Shopware.Utils.createId()}-entity-results`,
        };
    },
});
