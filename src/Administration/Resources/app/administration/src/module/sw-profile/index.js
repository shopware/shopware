import './extension/sw-admin-menu';
import './page/sw-profile-index';
import './view/sw-profile-index-general';
import './view/sw-profile-index-search-preferences';
import './acl';

const { Module } = Shopware;

Module.register('sw-profile', {
    type: 'core',
    name: 'profile',
    title: 'sw-profile.general.headlineProfile',
    description: 'sw-profile.general.description',
    color: '#9AA8B5',
    icon: 'default-avatar-single',
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
