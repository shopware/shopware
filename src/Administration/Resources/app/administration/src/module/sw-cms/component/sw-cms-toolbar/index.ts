import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package discovery
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-cms-toolbar',
    load: () => import('./src/sw-cms-toolbar'),
    public: {
        slots: [
            'title',
            'tools',
            'language-switch',
            'actions',
            'sidebar',
        ],
        blocks: [
            'sw_cms_toolbar',
            'sw_cms_toolbar_title',
            'sw_cms_toolbar_slot_title',
            'sw_cms_toolbar_tools',
            'sw_cms_toolbar_slot_tools',
            'sw_cms_toolbar_language_switch',
            'sw_cms_toolbar_slot_language_switch',
            'sw_cms_toolbar_actions',
            'sw_cms_toolbar_slot_actions',
            'sw_cms_toolbar_sidebar',
            'sw_cms_toolbar_slot_sidebar',
        ],
    },
});
