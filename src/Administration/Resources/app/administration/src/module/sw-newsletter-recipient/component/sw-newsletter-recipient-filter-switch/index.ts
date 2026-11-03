import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package after-sales
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-newsletter-recipient-filter-switch',
    load: () => import('./src/sw-newsletter-recipient-filter-switch'),
    public: {
        props: [
            'id',
            'label',
            'group',
        ],
        emits: [
            'update:value',
        ],
        slots: [
            'field',
        ],
        blocks: [
            'sw_newsletter_recipient_filter_switch',
            'sw_newsletter_recipient_filter_switch_field',
        ],
        methods: [
            'onChange',
        ],
    },
});
