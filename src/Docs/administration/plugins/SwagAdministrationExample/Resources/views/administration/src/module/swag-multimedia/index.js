import { Module } from 'src/core/shopware';
import './page/swag-multimedia-index';

Module.register('swag-multimedia', {
    type: 'plugin',
    name: 'Multimedia module',
    description: 'This is a example module',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#d0e83a',
    icon: 'default-device-headset',

    routes: {
        index: {
            components: {
                default: 'swag-multimedia-index'
            },
            path: 'index'
        }
    },

    navigation: [{
        label: 'swag-multimedia.mainMenuItemGeneral',
        color: '#d0e83a',
        path: 'swag.multimedia.index',
        icon: 'default-device-headset'
    }]
});
