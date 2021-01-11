import template from './sw-extension-meteor-page-context-menu-item.html.twig';
import './sw-extension-meteor-page-context-menu-item.scss';

const { Component } = Shopware;

Component.register('sw-extension-meteor-page-context-menu-item', {
    template,

    props: {
        menuEntry: {
            type: Object,
            required: true
        }
    },

    computed: {
        iconClasses() {
            return {
                'is--hidden': !this.menuEntry.hasCollapsedChildren
            };
        }
    }
});
