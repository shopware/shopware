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
    name: 'sw-mail-template-view-header-footer',
    load: () => import('./src/sw-mail-template-view-header-footer'),
    public: {
        blocks: [
            'sw_mail_template_view_header_footer',
            'sw_mail_template_view_header_footer_list',
        ],
        methods: [
            'getList',
        ],
    },
});
