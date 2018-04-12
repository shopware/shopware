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
        menuOffset: {
            type: Number,
            required: false,
            default: 32
        }
    },

    data() {
        return {
            showMenu: this.showMenuOnStartup,
            positionTop: 0,
            positionLeft: 0
        };
    },

    methods: {
        onToggleMenu() {
            this.positionTop = this.$el.offsetTop;
            this.positionLeft = this.$el.offsetLeft;
            this.showMenu = !this.showMenu;

            console.log(this.$el.offsetTop);

            if (this.showMenu) {
                document.body.appendChild(this.$children[1].$el);
            } else {
                document.body.removeChild(this.$children[1].$el);
            }
        }
    }
});
