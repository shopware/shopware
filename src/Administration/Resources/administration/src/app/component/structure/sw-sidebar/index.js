import './sw-sidebar.less';
import template from './sw-sidebar.html.twig';

Shopware.Component.register('sw-sidebar', {
    inject: ['menuService'],
    template,

    computed: {
        mainMenuEntries() {
            return this.menuService.getMainMenu();
        }
    },

    methods: {
        getIconName(name) {
            return `icon-${name}`;
        }
    }
});
