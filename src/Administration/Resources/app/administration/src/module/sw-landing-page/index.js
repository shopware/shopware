import '../sw-category/page/sw-category-detail';
import defaultSearchConfiguration from './default-search-configuration';

const { Module } = Shopware;

Module.register('sw-landing-page', {
    type: 'core',
    name: 'landing_page',
    title: 'sw-landing-page.general.mainMenuItemIndex',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#57D9A3',
    icon: 'default-symbol-products',
    favicon: 'icon-module-products.png',
    entity: 'landing_page',

    routes: {
        index: {
            component: 'sw-category-detail',
            path: 'index',
            redirect: {
                name: 'sw.category.detail.base',
            },
        },
    },

    defaultSearchConfiguration,
});
