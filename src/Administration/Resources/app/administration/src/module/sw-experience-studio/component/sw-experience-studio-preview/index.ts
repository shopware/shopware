import template from './sw-experience-studio-preview.html.twig';
import './sw-experience-studio-preview.scss';

type Viewport = 'mobile' | 'tablet-landscape' | 'desktop';

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
        viewport: {
            type: String,
            required: false,
            default: 'desktop',
        },
        salesChannelId: {
            type: String,
            required: false,
            default: null,
        },
    },

    computed: {
        viewportClass(): string {
            return `is--${this.viewport as Viewport}`;
        },
    },
});
