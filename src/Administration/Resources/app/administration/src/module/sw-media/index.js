/**
 * @sw-package discovery
 */
import './mixin/media-grid-listener/register';
import './mixin/media-sidebar-modal-mixin/register';
import './mixin/video-cover/register';
import './acl';
import defaultSearchConfiguration from './default-search-configuration';
import './component/sw-media-collapse/register';
import './component/sidebar/sw-media-quickinfo-usage/register';
import './component/sidebar/sw-media-sidebar/register';
import './component/sidebar/sw-media-tag/register';
import './component/sw-media-breadcrumbs/register';
import './component/sw-media-display-options/register';
import './component/sw-media-grid/register';
import './component/sw-media-modal-v2/register';
import './component/sw-media-save-modal/register';
import './page/sw-media-index/register';

const { Module } = Shopware;

/* eslint-disable sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register(
    'sw-media-quickinfo-metadata-item',
    () => import('./component/sidebar/sw-media-quickinfo-metadata-item'),
);
Shopware.Component.register('sw-media-folder-info', () => import('./component/sidebar/sw-media-folder-info'));
Shopware.Component.register('sw-media-quickinfo', () => import('./component/sidebar/sw-media-quickinfo'));
Shopware.Component.register('sw-media-quickinfo-multiple', () => import('./component/sidebar/sw-media-quickinfo-multiple'));
Shopware.Component.register('sw-media-library', () => import('./component/sw-media-library'));

/* eslint-enable sw-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-media', {
    type: 'core',
    name: 'media',
    title: 'sw-media.general.mainMenuItemGeneral',
    description: 'sw-media.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--sw-color-module-pink-default)',
    icon: 'regular-image',
    favicon: 'icon-module-content.svg',
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
                        routeFolderId: route.params.folderId || null,
                    };
                },
            },
            meta: {
                privilege: 'media.viewer',
            },
        },
    },

    navigation: [
        {
            id: 'sw-media',
            label: 'sw-media.general.mainMenuItemGeneral',
            color: 'var(--sw-color-module-pink-default)',
            icon: 'regular-image',
            path: 'sw.media.index',
            position: 20,
            parent: 'sw-content',
            privilege: 'media.viewer',
        },
    ],

    defaultSearchConfiguration,
});
