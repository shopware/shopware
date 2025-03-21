import template from './sw-cms-stage-add-section.html.twig';
import './sw-cms-stage-add-section.scss';

/**
 * @private
 * @sw-package discovery
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    emits: ['stage-section-add'],

    props: {
        forceChoose: {
            type: Boolean,
            required: false,
            default() {
                return false;
            },
        },

        disabled: {
            type: Boolean,
            required: false,
            default() {
                return false;
            },
        },
    },

    data() {
        return {
            showSelection: this.forceChoose,
        };
    },

    computed: {
        componentClasses() {
            return {
                'is--disabled': this.disabled,
            };
        },
    },

    methods: {
        onAddSection(type: string) {
            this.$emit('stage-section-add', type);
            this.showSelection = false;
        },

        toggleSelection() {
            if (this.disabled) {
                return;
            }
            this.showSelection = !this.showSelection;
        },
    },
});
