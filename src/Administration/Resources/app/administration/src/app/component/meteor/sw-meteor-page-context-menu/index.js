import template from './sw-meteor-page-context-menu.html.twig';
import './sw-meteor-page-context-menu.scss';

const { Component } = Shopware;

/**
 * @private
 */
Component.register('sw-meteor-page-context-menu', {
    template,

    props: {
        menuEntry: {
            type: Object,
            required: true
        }
    },

    computed: {
        parentNode() {
            return this.menuEntry.parentNode;
        },

        isRootMenu() {
            return this.menuEntry.depth === 1 ||
                (this.menuEntry.depth === 2 && !this.menuEntry.collapsed);
        },
        subMenuItems() {
            return this.menuEntry.collapsedChildren;
        }
    }
});
