import defaultSearchConfiguration from './default-search-configuration';

import './acl';
import './store/experience-studio-editor.store';
import './store/experience-studio-element-type.store';
import './store/experience-studio-style-option.store';

/**
 * @private
 */
Shopware.Component.register('sw-experience-studio-list', () => import('./page/sw-experience-studio-list'));

/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.register('sw-experience-studio-detail', () => import('./page/sw-experience-studio-detail'));

/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.extend(
    'sw-experience-studio-create',
    'sw-experience-studio-detail',
    () => import('./page/sw-experience-studio-create'),
);

/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.register('sw-experience-studio-toolbar', () => import('./component/sw-experience-studio-toolbar'));

/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.register(
    'sw-experience-studio-sidebar-tree',
    () => import('./component/sw-experience-studio-sidebar-tree'),
);

/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.register(
    'sw-experience-studio-sidebar-tree-node',
    () => import('./component/sw-experience-studio-sidebar-tree-node'),
);

/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.register('sw-experience-studio-preview', () => import('./component/sw-experience-studio-preview'));

/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.register(
    'sw-experience-studio-preview-node',
    () => import('./component/sw-experience-studio-preview-node'),
);

/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.register(
    'sw-experience-studio-element-settings',
    () => import('./component/sw-experience-studio-element-settings'),
);

/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.register(
    'sw-experience-studio-settings-fields',
    () => import('./component/sw-experience-studio-settings-fields'),
);

/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.register(
    'sw-experience-studio-box-spacing-field',
    () => import('./component/sw-experience-studio-box-spacing-field'),
);

/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.register(
    'sw-experience-studio-media-collection-field',
    () => import('./component/sw-experience-studio-media-collection-field'),
);

/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.register(
    'sw-experience-studio-element-picker',
    () => import('./component/sw-experience-studio-element-picker'),
);

/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.register(
    'sw-experience-studio-create-wizard',
    () => import('./component/sw-experience-studio-create-wizard'),
);

/**
 * @private
 * @sw-package discovery
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopware.Module.register('sw-experience-studio', {
    type: 'core',
    name: 'experience-studio',
    title: 'sw-experience-studio.general.mainMenuItemGeneral',
    description: 'sw-experience-studio.general.descriptionTextModule',
    color: 'var(--color-pink-500)',
    icon: 'regular-palette',
    favicon: 'icon-module-content.png',
    entity: 'content_layout',

    routes: {
        index: {
            component: 'sw-experience-studio-list',
            path: 'index',
            meta: {
                privilege: 'experience_studio.viewer',
            },
        },
        detail: {
            component: 'sw-experience-studio-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.experience.studio.index',
                privilege: 'experience_studio.viewer',
            },
        },
        create: {
            component: 'sw-experience-studio-create',
            path: 'create/:id?',
            meta: {
                parentPath: 'sw.experience.studio.index',
                privilege: 'experience_studio.creator',
            },
        },
    },

    navigation: [
        {
            id: 'sw-experience-studio',
            label: 'sw-experience-studio.general.mainMenuItemGeneral',
            color: 'var(--color-pink-500)',
            path: 'sw.experience.studio.index',
            icon: 'regular-palette',
            position: 5,
            parent: 'sw-content',
            privilege: 'experience_studio.viewer',
        },
    ],

    defaultSearchConfiguration,
});
