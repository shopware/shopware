/**
 * @sw-package framework
 * @private
 */
// eslint-disable-next-line @typescript-eslint/no-unused-vars
import { VueWrapper } from '@vue/test-utils';

declare module '@vue/test-utils' {
    interface VueWrapper<T> {
        findByText(selector: string, text: string): VueWrapper<T> | null;
        findByAriaLabel(selector: string, text: string): VueWrapper<T> | null;
    }
}
