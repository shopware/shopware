import { Component } from 'src/core/shopware';
import template from './sw-modal.html.twig';
import './sw-modal.less';

Component.register('sw-modal', {
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
            this.setFocusToModal();
        },

        destroyedComponent() {
            document.removeEventListener('keyup', this.closeModalOnEscapeKey);
        },

        setFocusToModal() {
            this.$el.querySelector('.sw-modal__dialog').focus();
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
});
