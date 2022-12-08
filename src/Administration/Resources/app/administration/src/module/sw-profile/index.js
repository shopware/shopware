/**
 * @package system-settings
 */
import './extension/sw-admin-menu';
import './acl';

const { Module } = Shopware;

/* eslint-disable max-len, sw-deprecation-rules/private-feature-declarations */
Shopware.Component.register('sw-profile-index', () => import('./page/sw-profile-index'));
Shopware.Component.register('sw-profile-index-general', () => import('./view/sw-profile-index-general'));
Shopware.Component.register('sw-profile-index-search-preferences', () => import('./view/sw-profile-index-search-preferences'));
/* eslint-enable max-len, sw-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-profile', {
    type: 'core',
    name: 'profile',
    title: 'sw-profile.general.headlineProfile',
    description: 'sw-profile.general.description',
    color: '#9AA8B5',
    icon: 'regular-user',
    entity: 'user',

    routes: {
        index: {
            component: 'sw-profile-index',
            path: 'index',
            redirect: {
                name: 'sw.profile.index.general',
            },
            meta: {
                privilege: 'user.update_profile',
            },
            children: {
                general: {
                    component: 'sw-profile-index-general',
                    path: 'general',
                    meta: {
                        parentPath: 'sw.profile.index',
                        privilege: 'user.update_profile',
                    },
                },
                searchPreferences: {
                    component: 'sw-profile-index-search-preferences',
                    path: 'search-preferences',
                    meta: {
                        parentPath: 'sw.profile.index',
                        privilege: 'user.update_profile',
                    },
                },
            },
        },
    },
});
