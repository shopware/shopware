import 'src/app/component/organism/sw-sidebar/sw-sidebar.less';
import template from 'src/app/component/organism/sw-sidebar/sw-sidebar.html.twig';

export default Shopware.Component.register('sw-sidebar', {
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
