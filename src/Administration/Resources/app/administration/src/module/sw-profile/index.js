import './extension/sw-admin-menu';
import './page/sw-profile-index';
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
            meta: {
                privilege: 'user.update_profile'
            }
        }
    }
});
