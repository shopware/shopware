import template from './sw-experience-studio-element-picker.html.twig';
import './sw-experience-studio-element-picker.scss';

/**
 * @private
 * @sw-package discovery
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        open: {
            type: Boolean,
            required: true,
        },
        title: {
            type: String,
            required: true,
        },
        elements: {
            type: Array,
            required: false,
            default: () => [],
        },
        top: {
            type: Number,
            required: false,
            default: 0,
        },
        left: {
            type: Number,
            required: false,
            default: 0,
        },
    },

    emits: [
        'close',
        'select',
    ],

    methods: {
        flyoutStyle(): { top: string; left: string } {
            return {
                top: `${this.top}px`,
                left: `${this.left}px`,
            };
        },

        onSelect(component: string): void {
            this.$emit('select', component);
        },
    },
});
