import './page/sw-event-action-list';
import './page/sw-event-action-detail';
import './component/sw-event-action-list-expand-labels';
import './component/sw-event-action-detail-recipients';
import './acl';

Shopware.Module.register('sw-event-action', {
    type: 'core',
    name: 'event-action',
    title: 'sw-event-action.general.mainMenuItemGeneral',
    description: 'sw-event-action.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#9AA8B5',
    icon: 'default-action-settings',
    favicon: 'icon-module-settings.png',
    entity: 'event_action',

    routes: {
        index: {
            component: 'sw-event-action-list',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'event_action.viewer',
            },
        },
        detail: {
            component: 'sw-event-action-detail',
            path: 'detail/:id',
            props: {
                default: (route) => ({ eventActionId: route.params.id }),
            },
            meta: {
                parentPath: 'sw.event.action.index',
                privilege: 'event_action.viewer',
            },
        },
        create: {
            component: 'sw-event-action-detail',
            path: 'create',
            meta: {
                parentPath: 'sw.event.action.index',
                privilege: 'event_action.creator',
            },
        },
    },

    settingsItem: {
        group: 'shop',
        to: 'sw.event.action.index',
        icon: 'default-action-sliders',
        privilege: 'event_action.viewer',
    },
});
