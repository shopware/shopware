import { Module } from 'src/core/shopware';
import './page/sw-mediamanager-index';
import './page/sw-mediamanager-catalog';
import './component/sidebar/sw-mediamanager-sidebar';
import './component/sw-media-grid-catalog-item';

Module.register('sw-mediamanager', {
    type: 'core',
    name: 'Mediamanager',
    description: 'sw-mediamanager.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#ef734a',
    icon: 'default-object-image',

    routes: {
        index: {
            components: {
                default: 'sw-mediamanager-index'
            },
            path: 'index'
        },
        'catalog-content': {
            components: {
                default: 'sw-mediamanager-catalog'
            },
            path: 'catalog/:id'
        }
    },

    navigation: [{
        id: 'sw-mediamanager',
        label: 'sw-mediamanager.general.mainMenuItemGeneral',
        color: '#ef734a',
        icon: 'default-object-image',
        path: 'sw.mediamanager.index'
    }]
});
