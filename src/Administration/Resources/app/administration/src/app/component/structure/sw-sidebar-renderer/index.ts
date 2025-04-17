/**
 * @sw-package framework
 */

import { computed } from 'vue';
import template from './sw-sidebar-renderer.html.twig';
import './sw-sidebar-renderer.scss';

const { Component } = Shopware;

/**
 * @private
 */
Component.register('sw-sidebar-renderer', {
    template,

    setup() {
        const activeSidebar = computed(() => {
            return Shopware.Store.get('sidebar').getActiveSidebar;
        });

        const sidebars = computed(() => {
            return Shopware.Store.get('sidebar').sidebars;
        });

        const closeSidebar = (locationId: string) => {
            Shopware.Store.get('sidebar').closeSidebar(locationId);
        };

        return {
            activeSidebar,
            sidebars,
            closeSidebar,
        };
    },
});
