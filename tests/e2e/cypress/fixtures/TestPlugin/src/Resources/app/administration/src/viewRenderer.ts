import Vue from 'vue';
import VueI18n from 'vue-i18n'
import { location } from '@shopware-ag/meteor-admin-sdk';
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
        'CardTab1': () => import('./views/card-tabs/tab-1'),
        'CardTab2': () => import('./views/card-tabs/tab-2'),
        // data/dataset
        'DataDataset': () => import('./views/data/dataset'),
    },
    template: `
        <LocationIndex v-if="location.is('location-index')"></LocationIndex>
        <CardTab1 v-else-if="location.is('card-tab-1')"></CardTab1>
        <CardTab2 v-else-if="location.is('card-tab-2')"></CardTab2>
        <UiModals v-else-if="location.is('ui-modals')"></UiModals>
        <DataDataset v-else-if="location.is('data-dataset')"></DataDataset>
        <UiModalsModalContent v-else-if="location.is('ui-modals-modal-content')"></UiModalsModalContent>
        <UiMainModuleAddMainModule v-else-if="location.is('ui-main-module-add-main-module')"></UiMainModuleAddMainModule>
        <UiMenuItemAddMenuItem v-else-if="location.is('ui-menu-item-add-menu-item')"></UiMenuItemAddMenuItem>
        <UiMenuItemAddMenuItemWithSearchBar v-else-if="location.is('ui-menu-item-add-menu-item-with-searchbar')"></UiMenuItemAddMenuItemWithSearchBar>
    `,
})
