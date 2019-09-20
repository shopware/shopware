import template from './sw-text-editor-toolbar-button.html.twig';
import './sw-text-editor-toolbar-button.scss';

const { Component } = Shopware;

/**
 * @private
 */
Component.register('sw-text-editor-toolbar-button', {
    template,

    props: {
        buttonConfig: {
            type: Object,
            required: true
        },

        disabled: {
            type: Boolean,
            requred: false,
            default: false
        }
    },

    computed: {
        classes() {
            return {
                'is--active': !!this.buttonConfig.active || this.buttonConfig.expanded,
                'is--disabled': !!this.disabled
            };
        }
    },

    methods: {
        buttonHandler(event, button) {
            if (this.disabled) {
                return null;
            }

            return button.children || button.type === 'link' || button.type === 'table' || button.type === 'foreColor' ?
                this.onToggleMenu(event, button) :
                this.handleButtonClick(button);
        },

        childActive(child) {
            return {
                'is--active': !!child.active
            };
        },

        handleButtonClick(button, parent = null) {
            if (this.disabled) {
                return;
            }

            this.$emit('button-click', button, parent);
        },

        onToggleMenu(event, button) {
            if (!['link', 'table', 'foreColor'].includes(button.type) && !button.children) {
                return;
            }

            if (button.type === 'foreColor' && event.target.closest('.sw-colorpicker__colorpicker')) {
                return;
            }

            if (button.type === 'link' && event.target.closest('.sw-text-editor-toolbar-button__link-menu')) {
                return;
            }

            if (button.type === 'table' && event.target.closest('.sw-text-editor-toolbar-button__table-menu')) {
                return;
            }

            if (event.target.closest('.sw-text-editor-toolbar-button__children')) {
                return;
            }

            this.$emit('menu-toggle', event, button);
        }
    }
});
