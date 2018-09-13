import { Component } from 'src/core/shopware';
import template from './sw-context-button.html.twig';
import './sw-context-button.less';

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

        menuOffsetTop: {
            type: Number,
            required: false,
            default: 10
        },

        menuOffsetLeft: {
            type: Number,
            required: false,
            default: 15
        },

        menuAlign: {
            type: String,
            required: false,
            default: 'right',
            validator(value) {
                if (!value.length) {
                    return true;
                }
                return ['right', 'left'].includes(value);
            }
        }
    },

    data() {
        return {
            showMenu: this.showMenuOnStartup,
            positionTop: 0,
            positionLeft: 0,
            paddingTop: 0,
            menuUuid: 0
        };
    },

    computed: {
        menuStyles() {
            return {
                left: `${this.positionLeft}px`,
                top: `${this.positionTop}px`,
                display: this.showMenu ? 'block' : 'none',
                width: `${this.menuWidth}px`,
                'padding-top': `${this.paddingTop}px`
            };
        },

        contextButtonClass() {
            return {
                'is--active': this.showMenu
            };
        },

        contextMenuClass() {
            return {
                'is--left-align': this.menuAlign === 'left'
            };
        }
    },

    mounted() {
        this.mountedComponent();
    },

    beforeDestroy() {
        this.beforeDestroyComponent();
    },

    methods: {
        mountedComponent() {
            if (this.showMenu) {
                return;
            }
            this.removeMenuFromBody();
        },

        beforeDestroyComponent() {
            this.removeMenuFromBody();
        },

        openMenu() {
            const boundingBox = this.$el.getBoundingClientRect();
            const secureOffset = 5;

            this.positionTop = boundingBox.top - secureOffset;
            if (this.menuAlign === 'left') {
                this.positionLeft = boundingBox.left - secureOffset;
            } else {
                this.positionLeft = (boundingBox.left + boundingBox.width + this.menuOffsetLeft) - this.menuWidth;
            }
            this.paddingTop = boundingBox.height + secureOffset + this.menuOffsetTop;

            this.addMenuToBody();
            this.showMenu = true;
        },

        closeMenu(event) {
            const el = this.$refs.swContextButton;
            const target = event.target;

            if ((el !== target) && !el.contains(target) && !target.classList.contains('is--disabled')) {
                this.showMenu = false;
                this.removeMenuFromBody();
            }
        },

        addMenuToBody() {
            const menuEl = this.$refs.swContextMenu;

            if (menuEl) {
                document.body.appendChild(menuEl.$el);
                document.addEventListener('click', this.closeMenu);
            }
        },

        removeMenuFromBody() {
            const menuEl = this.$refs.swContextMenu;

            if (menuEl) {
                document.removeEventListener('click', this.closeMenu);
                menuEl.$el.remove();
            }
        }
    }
});
