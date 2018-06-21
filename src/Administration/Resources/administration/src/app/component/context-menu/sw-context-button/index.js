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
            default: 200
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
        }
    },

    beforeDestroy() {
        this.removeMenuFromBody();
    },

    methods: {
        openMenu() {
            const boundingBox = this.$el.getBoundingClientRect();
            const secureOffset = 5;

            this.positionTop = boundingBox.top - secureOffset;
            this.positionLeft = (boundingBox.left + boundingBox.width + this.menuOffsetLeft) - this.menuWidth;
            this.paddingTop = boundingBox.height + secureOffset + this.menuOffsetTop;

            this.showMenu = true;

            this.addMenuToBody();
        },

        closeMenu(e) {
            const el = this.$refs.swContextButton;
            const target = e.target;

            if ((el !== target) &&
                !el.contains(target) &&
                !target.classList.contains('sw-context-menu__content') &&
                !target.classList.contains('sw-context-menu-item')
            ) {
                this.showMenu = false;
                this.removeMenuFromBody();
            }
        },

        addMenuToBody() {
            const menuEl = this.$children[1];

            if (menuEl) {
                document.body.appendChild(menuEl.$el);
                document.addEventListener('click', this.closeMenu);
            }
        },

        removeMenuFromBody() {
            const menuEl = this.$children[1];

            if (menuEl) {
                document.removeEventListener('click', this.closeMenu);
                menuEl.$el.remove();
            }
        }
    }
});
