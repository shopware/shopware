import template from './sw-cms-stage-add-section.html.twig';
import './sw-cms-stage-add-section.scss';

/**
 * @private
 * @package content
 */
export default {
    template,

    props: {
        forceChoose: {
            type: Boolean,
            required: false,
            default: false,
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
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
        onAddSection(type) {
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
};
