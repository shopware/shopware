import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package framework
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-error',
    load: () => import('./src/sw-error'),
    public: {
        props: [
            'errorObject',
            'routerLink',
            'linkText',
        ],
        slots: [
            'default',
        ],
        blocks: [
            'sw_error',
            'sw_error_illustration',
            'sw_error_contend',
            'sw_error_status_code',
            'sw_error_message',
            'sw_error_stack',
            'sw_error_link',
        ],
        computed: [
            'error',
            'imagePath',
            'message',
            'statusCode',
            'showStack',
            'showLink',
        ],
        methods: [
            'createdComponent',
        ],
    },
});
