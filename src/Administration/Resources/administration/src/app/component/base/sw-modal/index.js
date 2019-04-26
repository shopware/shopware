import utils from 'src/core/service/util.service';
import template from './sw-modal.html.twig';
import './sw-modal.scss';

/**
 * @public
 * @description Modal box component which can be displayed in different variants and sizes
 * @status ready
 * @example-type static
 * @component-example
 * <sw-modal title="Modal box title" selector=".example .panel--content" style="height: 400px">
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

    data() {
        return {
            id: utils.createId()
        };
    },

    computed: {
        modalClasses() {
            return {
                [`sw-modal--${this.variant}`]: (this.variant && !this.size)
            };
        },

        identifierClass() {
            return `sw-modal--${this.id}`;
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
            const targetEl = document.querySelector(this.selector);
            targetEl.appendChild(this.$el);

            this.setFocusToModal();
        },

        destroyedComponent() {
            document.querySelector('.sw-modal__dialog').focus();
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
            this.$emit('modal-close');
        },

        closeModalOnEscapeKey(event) {
            if (!event.target.classList.contains('sw-modal__dialog') || event.target !== document.activeElement) {
                return;
            }

            if (event.key === 'Escape' || event.keyCode === 27) {
                this.closeModal();
            }
        }
    }
};
