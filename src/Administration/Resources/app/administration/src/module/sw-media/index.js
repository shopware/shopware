import './mixin/media-grid-listener.mixin';
import './mixin/media-sidebar-modal.mixin';
import './page/sw-media-index';
import './component/sw-media-grid';
import './component/sidebar/sw-media-sidebar';
import './component/sidebar/sw-media-quickinfo-metadata-item';
import './component/sidebar/sw-media-quickinfo-usage';
import './component/sw-media-collapse';
import './component/sidebar/sw-media-folder-info';
import './component/sidebar/sw-media-quickinfo';
import './component/sidebar/sw-media-quickinfo-multiple';
import './component/sidebar/sw-media-tag';
import './component/sw-media-display-options';
import './component/sw-media-breadcrumbs';
import './component/sw-media-library';
import './component/sw-media-modal-v2';
import './acl';

const { Module } = Shopware;

Module.register('sw-media', {
    type: 'core',
    name: 'media',
    title: 'sw-media.general.mainMenuItemGeneral',
    description: 'sw-media.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#ff68b4',
    icon: 'default-object-image',
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
        icon: 'default-object-image',
        path: 'sw.media.index',
        position: 20,
        parent: 'sw-content',
        privilege: 'media.viewer',
    }],
});
