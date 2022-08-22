import Vue from 'vue';
import VueI18n from 'vue-i18n'
import { location } from '@shopware-ag/admin-extension-sdk';
import '@shopware-ag/meteor-component-library/dist/style.css';

Vue.use(VueI18n);

// watch for height changes
location.startAutoResizer();

// start app views
const app = new Vue({
    el: '#app',
    data() {
        return { location }
    },
    components: {
        // ui/main-module/add-main-module
        'UiMainModuleAddMainModule': () => import('./views/ui/main-module/add-main-module'),
        // ui/menu-item/
        'UiMenuItemAddMenuItem': () => import('./views/ui/menu-item/add-menu-item'),
        'UiMenuItemAddMenuItemWithSearchBar': () => import('./views/ui/menu-item/add-menu-item-with-searchbar'),
        // ui/modals
        'UiModals': () => import('./views/ui/modals/modals'),
        'UiModalsModalContent': () => import('./views/ui/modals/modal-content'),
        // location/general
        'LocationIndex': () => import('./views/location/index'),
    },
    template: `
        <LocationIndex v-if="location.is('location-index')"></LocationIndex>
        <UiModals v-else-if="location.is('ui-modals')"></UiModals>
        <UiModalsModalContent v-else-if="location.is('ui-modals-modal-content')"></UiModalsModalContent>
        <UiMainModuleAddMainModule v-else-if="location.is('ui-main-module-add-main-module')"></UiMainModuleAddMainModule>
        <UiMenuItemAddMenuItem v-else-if="location.is('ui-menu-item-add-menu-item')"></UiMenuItemAddMenuItem>
        <UiMenuItemAddMenuItemWithSearchBar v-else-if="location.is('ui-menu-item-add-menu-item-with-searchbar')"></UiMenuItemAddMenuItemWithSearchBar>
    `,
})
