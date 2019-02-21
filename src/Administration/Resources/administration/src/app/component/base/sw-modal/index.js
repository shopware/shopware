import template from './sw-modal.html.twig';
import './sw-modal.scss';

/**
 * @public
 * @description Modal box component which can be displayed in different variants and sizes
 * @status ready
 * @example-type static
 * @component-example
 * <sw-modal title="Modal box title" selector=".example .panel--content">
 *     Lorem Ipsum
 * </sw-modal>
 */
export default {
    name: 'sw-modal',
    template,

    inheritAttrs: false,

    props: {
        title: {
            type: String,
            default: ''
        },

        size: {
            type: String,
            default: ''
        },

        variant: {
            type: String,
            required: false,
            default: 'default',
            validValues: ['default', 'small', 'large', 'full'],
            validator(value) {
                if (!value.length) {
                    return true;
                }
                return ['default', 'small', 'large', 'full'].includes(value);
            }
        },

        isLoading: {
            type: Boolean,
            required: false,
            default: false
        },

        selector: {
            type: String,
            required: false,
            default: 'body'
        },

        showHeader: {
            type: Boolean,
            required: false,
            default: true
        }
    },

    computed: {
        modalClasses() {
            return {
                [`sw-modal--${this.variant}`]: (this.variant && !this.size)
            };
        },

        hasFooterSlot() {
            return !!this.$slots['modal-footer'];
        }
    },

    mounted() {
        this.mountedComponent();
    },

    destroyed() {
        this.destroyedComponent();
    },

    methods: {
        mountedComponent() {
            document.addEventListener('keyup', this.closeModalOnEscapeKey);
            const targetEl = document.querySelector(this.selector);
            targetEl.appendChild(this.$el);

            this.setFocusToModal();
        },

        destroyedComponent() {
            document.removeEventListener('keyup', this.closeModalOnEscapeKey);
        },

        setFocusToModal() {
            this.$el.querySelector('.sw-modal__dialog').focus();
        },

        closeModalOnClickOutside(domEvent) {
            if (!this.$refs.dialog || !this.$refs.dialog.contains(domEvent.target)) {
                this.closeModal();
            }
        },

        closeModal() {
            this.$emit('closeModal');
        },

        closeModalOnEscapeKey(event) {
            if (event.key === 'Escape' || event.keyCode === 27) {
                this.closeModal();
            }
        }
    }
};
