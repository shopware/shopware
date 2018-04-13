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
            default: 255
        },
        menuOffsetTop: {
            type: Number,
            required: false,
            default: 10
        },
        menuOffsetLeft: {
            type: Number,
            required: false,
            default: 22
        }
    },

    data() {
        return {
            showMenu: this.showMenuOnStartup,
            positionTop: 0,
            positionLeft: 0,
            paddingTop: 0
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

        closeMenu() {
            this.showMenu = false;

            this.removeMenuFromBody();
        },

        addMenuToBody() {
            if (this.$children[1]) {
                document.body.appendChild(this.$children[1].$el);
                this.$children[1].$el.addEventListener('mouseleave', this.closeMenu);
            }
        },

        removeMenuFromBody() {
            if (this.$children[1]) {
                this.$children[1].$el.removeEventListener('mouseleave', this.closeMenu);
                this.$children[1].$el.remove();
            }
        }
    }
});
