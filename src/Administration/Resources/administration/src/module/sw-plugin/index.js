import { Module } from 'src/core/shopware';
import { NEXT1223 } from 'src/flag/feature_next1223';
import './page/sw-plugin-list';
import './page/sw-plugin-license-list';
import './component/sw-plugin-file-upload';
import './component/sw-plugin-store-login';

Module.register('sw-plugin', {
    flag: NEXT1223,
    type: 'core',
    name: 'sw-plugin.general.mainMenuItemGeneral',
    description: 'sw-plugin.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#54d6ed',
    icon: 'default-object-plug',
    entity: 'plugin',

    routes: {
        index: {
            components: {
                default: 'sw-plugin-list'
            },
            path: 'index',
            alias: 'list'
        },
        licenseList: {
            component: 'sw-plugin-license-list',
            path: 'licenses'
        }
    },

    navigation: [{
        id: 'sw-plugin',
        label: 'sw-plugin.general.mainMenuItemGeneral',
        color: '#54d6ed',
        path: 'sw.plugin.index',
        icon: 'default-object-plug',
        position: 9999
    }, {
        path: 'sw.plugin.index',
        label: 'sw-plugin.general.mainMenuPluginList',
        parent: 'sw-plugin'
    }, {
        path: 'sw.plugin.licenseList',
        label: 'sw-plugin.general.mainMenuLicenseList',
        parent: 'sw-plugin'
    }]
});
