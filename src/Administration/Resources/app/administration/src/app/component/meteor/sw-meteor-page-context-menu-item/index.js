import template from './sw-meteor-page-context-menu-item.html.twig';
import './sw-meteor-page-context-menu-item.scss';

const { Component } = Shopware;

/**
 * @private
 */
Component.register('sw-meteor-page-context-menu-item', {
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
