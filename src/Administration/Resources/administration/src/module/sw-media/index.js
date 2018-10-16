import { Module } from 'src/core/shopware';
import './mixin/mediagrid-listener.mixin';
import './page/sw-media-index';
import './page/sw-media-catalog';
import './component/sidebar/sw-media-sidebar';

Module.register('sw-media', {
    type: 'core',
    name: 'Media',
    description: 'sw-media.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#FFD700',
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
        color: '#FFD700',
        icon: 'default-object-image',
        path: 'sw.media.index',
        position: 40
    }]
});
