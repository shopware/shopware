import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package after-sales
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-mail-template-view-templates',
    load: () => import('./src/sw-mail-template-view-templates'),
    public: {
        blocks: [
            'sw_mail_template_view_templates',
            'sw_mail_template_view_templates_list',
        ],
        methods: [
            'getList',
        ],
    },
});
