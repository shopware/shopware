import template from './sw-context-button.html.twig';
import './sw-context-button.scss';

const { Component } = Shopware;

/**
 * @public
 * @status ready
 * @example-type dynamic
 * @component-example
 * <sw-context-button>
 *     <sw-context-menu-item>
 *         Example item
 *     </sw-context-menu-item>
 * </sw-context-button>
 */
Component.register('sw-context-button', {
    template,

    props: {
        showMenuOnStartup: {
            type: Boolean,
            required: false,
            default: false
        },

        menuWidth: {
            type: Number,
            required: false,
            default: 220
        },

        menuHorizontalAlign: {
            type: String,
            required: false,
            default: 'right',
            validator(value) {
                if (!value.length) {
                    return true;
                }
                return ['right', 'left'].includes(value);
            }
        },

        menuVerticalAlign: {
            type: String,
            required: false,
            default: 'bottom',
            validator(value) {
                if (!value.length) {
                    return true;
                }
                return ['bottom', 'top'].includes(value);
            }
        },

        icon: {
            type: String,
            required: false,
            default: 'small-more'
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false
        },

        autoClose: {
            type: Boolean,
            required: false,
            default: true
        },

        additionalContextMenuClasses: {
            type: Object,
            required: false,
            default() {
                return {};
            }
        },

        zIndex: {
            type: Number,
            required: false,
            default: 9000
        }
    },

    data() {
        return {
            showMenu: this.showMenuOnStartup
        };
    },

    computed: {
        menuStyles() {
            return {
                right: '-34px',
                top: '10px',
                width: `${this.menuWidth}px`
            };
        },

        contextClass() {
            return {
                'is--disabled': this.disabled,
                'is--active': this.showMenu
            };
        },

        contextButtonClass() {
            return {
                'is--active': this.showMenu
            };
        },

        contextMenuClass() {
            return {
                'is--left-align': this.menuHorizontalAlign === 'left',
                'is--top-align': this.menuVerticalAlign === 'top',
                ...this.additionalContextMenuClasses
            };
        }
    },

    methods: {
        onClickButton() {
            if (this.disabled) {
                return;
            }

            if (this.showMenu) {
                this.closeMenu();
            } else {
                this.openMenu();
            }
        },

        openMenu() {
            this.showMenu = true;
            document.addEventListener('click', this.handleClickEvent);
        },

        handleClickEvent(event) {
            // when target is disabled dont close the context menu item
            const isTargetDisabled = event && event.target.classList.contains('is--disabled');
            if (isTargetDisabled) {
                return false;
            }

            // close menu when no context button exists (when component gets destroyed)
            const contextButton = this.$refs.swContextButton;
            if (!contextButton) {
                return this.closeMenu();
            }

            // check if the user clicked inside the context menu
            const clickedInside = contextButton ? contextButton.contains(event.target) : false;

            // only close the menu on inside clicks if autoclose is active
            const shouldCloseOnInsideClick = (this.autoClose && !clickedInside);

            // close menu when there is no native event (when vue event is triggered) or user clicked outside
            if ((!event || !event.target) || shouldCloseOnInsideClick) {
                return this.closeMenu();
            }

            return false;
        },

        closeMenu() {
            this.showMenu = false;
            document.removeEventListener('click', this.handleClickEvent);
        }
    }
});
