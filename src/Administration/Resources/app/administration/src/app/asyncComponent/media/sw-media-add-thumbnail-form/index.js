import template from './sw-media-add-thumbnail-form.html.twig';
import './sw-media-add-thumbnail-form.scss';

/**
 * @private
 * @package content
 */
export default {
    template,

    props: {
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            width: null,
            height: null,
            isLocked: true,
        };
    },

    computed: {
        lockedButtonClass() {
            return {
                'is--locked': this.isLocked,
            };
        },
    },

    methods: {
        onLockSwitch() {
            this.isLocked = !this.isLocked;
        },

        onAdd() {
            this.$emit('thumbnail-form-size-add', { width: this.width, height: this.height });
            this.width = null;
            this.height = null;
        },

        widthInputChanged(value) {
            if (this.isLocked) {
                this.height = value;
            }
            this.width = value;
            this.inputChanged();
        },

        heightInputChanged(value) {
            this.height = value;
            this.inputChanged();
        },

        inputChanged() {
            const { width = 0, height = 0 } = this;
            this.$emit('on-input', { width, height });
        },
    },
};
