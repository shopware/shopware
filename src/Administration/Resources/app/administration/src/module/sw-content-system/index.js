/**
 * @sw-package discovery
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopware.Component.register('sw-content-system-index', () => import('./page/sw-content-system-index'));

/**
 * @sw-package discovery
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopware.Module.register('sw-content-system', {
    type: 'core',
    name: 'content_system',
    title: 'sw-content-system.general.mainMenuItemGeneral',
    description: 'sw-content-system.general.descriptionTextModule',
    color: '#ff68b4',
    icon: 'regular-content',

    routes: {
        index: {
            component: 'sw-content-system-index',
            path: 'index',
            meta: {
                privilege: 'cms.viewer',
            },
        },
    },

    navigation: [
        {
            id: 'sw-content-system',
            label: 'sw-content-system.general.mainMenuItemGeneral',
            color: '#ff68b4',
            path: 'sw.content.system.index',
            icon: 'regular-content',
            position: 20,
            parent: 'sw-content',
            privilege: 'cms.viewer',
        },
    ],
});
