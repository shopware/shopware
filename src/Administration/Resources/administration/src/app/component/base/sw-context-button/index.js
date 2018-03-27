import { Component } from 'src/core/shopware';
import template from './sw-context-button.html.twig';
import './sw-context-button.less';

Component.register('sw-context-button', {
    template,

    props: {
        icon: {
            type: String,
            required: false,
            default: 'default-action-more-horizontal'
        },
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
            rightPos: 0
        };
    },

    methods: {
        onToggleMenu() {
            const width = this.$el.clientWidth;
            this.rightPos = `${(width / 2) - this.menuOffset}px`;
            this.showMenu = !this.showMenu;
        }
    }
});
