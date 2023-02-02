/**
 * @package content
 */
import './mixin/media-grid-listener.mixin';
import './mixin/media-sidebar-modal.mixin';
import './acl';
import defaultSearchConfiguration from './default-search-configuration';

const { Module } = Shopware;

/* eslint-disable max-len, sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-media-index', () => import('./page/sw-media-index'));
Shopware.Component.register('sw-media-grid', () => import('./component/sw-media-grid'));
Shopware.Component.register('sw-media-sidebar', () => import('./component/sidebar/sw-media-sidebar'));
Shopware.Component.register('sw-media-quickinfo-metadata-item', () => import('./component/sidebar/sw-media-quickinfo-metadata-item'));
Shopware.Component.register('sw-media-quickinfo-usage', () => import('./component/sidebar/sw-media-quickinfo-usage'));
Shopware.Component.extend('sw-media-collapse', 'sw-collapse', () => import('./component/sw-media-collapse'));
Shopware.Component.register('sw-media-folder-info', () => import('./component/sidebar/sw-media-folder-info'));
Shopware.Component.register('sw-media-quickinfo', () => import('./component/sidebar/sw-media-quickinfo'));
Shopware.Component.register('sw-media-quickinfo-multiple', () => import('./component/sidebar/sw-media-quickinfo-multiple'));
Shopware.Component.register('sw-media-tag', () => import('./component/sidebar/sw-media-tag'));
Shopware.Component.register('sw-media-display-options', () => import('./component/sw-media-display-options'));
Shopware.Component.register('sw-media-breadcrumbs', () => import('./component/sw-media-breadcrumbs'));
Shopware.Component.register('sw-media-library', () => import('./component/sw-media-library'));
Shopware.Component.register('sw-media-modal-v2', () => import('./component/sw-media-modal-v2'));
/* eslint-enable max-len, sw-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-media', {
    type: 'core',
    name: 'media',
    title: 'sw-media.general.mainMenuItemGeneral',
    description: 'sw-media.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#ff68b4',
    icon: 'regular-image',
    favicon: 'icon-module-content.png',
    entity: 'media',

    routes: {
        index: {
            components: {
                default: 'sw-media-index',
            },
            path: 'index/:folderId?',
            props: {
                default: (route) => {
                    return {
                        routeFolderId: route.params.folderId,
                    };
                },
            },
            meta: {
                privilege: 'media.viewer',
            },
        },
    },

    navigation: [{
        id: 'sw-media',
        label: 'sw-media.general.mainMenuItemGeneral',
        color: '#ff68b4',
        icon: 'regular-image',
        path: 'sw.media.index',
        position: 20,
        parent: 'sw-content',
        privilege: 'media.viewer',
    }],

    defaultSearchConfiguration,
});
