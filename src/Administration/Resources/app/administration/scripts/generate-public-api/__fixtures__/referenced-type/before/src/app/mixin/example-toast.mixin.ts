/**
 * @sw-package framework
 */
import { defineComponent } from 'vue';
import type { ExampleToast } from './example-toast.types';
import { EXAMPLE_TOAST_DEFAULT_DURATION } from './example-toast.types';

const { Mixin } = Shopware;

/**
 * @public
 */
export default Mixin.register(
    'example-toast',
    defineComponent({
        methods: {
            createToast(toast: ExampleToast): string | null {
                return Shopware.Store.get('notification').createNotification({
                    duration: EXAMPLE_TOAST_DEFAULT_DURATION,
                    ...toast,
                });
            },

            createToastError(toast: ExampleToast): void {
                void this.createToast({
                    variant: 'critical',
                    ...toast,
                });
            },
        },
    }),
);
