import { Module } from 'src/core/shopware';
import './page/sw-media-index';
import './page/sw-media-catalog';
import './component/sidebar/sw-media-sidebar';
import './component/sw-media-grid-catalog-item';

Module.register('sw-media', {
    type: 'core',
    name: 'Media',
    description: 'sw-media.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#ef734a',
    icon: 'default-object-image',

    routes: {
        index: {
            components: {
                default: 'sw-media-index'
            },
            path: 'index'
        },
        'catalog-content': {
            components: {
                default: 'sw-media-catalog'
            },
            path: 'catalog/:id'
        }
    },

    navigation: [{
        id: 'sw-media',
        label: 'sw-media.general.mainMenuItemGeneral',
        color: '#ef734a',
        icon: 'default-object-image',
        path: 'sw.media.index'
    }]
});
