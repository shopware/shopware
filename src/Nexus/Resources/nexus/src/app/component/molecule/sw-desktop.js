
import template from 'src/app/component/molecule/sw-desktop/sw-desktop.html.twig';

export default Shopware.ComponentFactory.register('sw-desktop', {
    inject: ['menuService'],
    template,
    computed: {
        mainMenuEntries() {
            return this.menuService.getMainMenu();
        }
    },

    methods: {
        getIconName(name) {
            return `icon--${name}`;
        }
    }
});
