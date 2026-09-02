/**
 * @sw-package framework
 */

import { getCurrentInstance, onScopeDispose } from 'vue';
import { registerShortcut } from 'src/core/helper/shortcut-registry.helper';

type DeviceHelper = { getSystemKey: () => string };

let deviceHelper: DeviceHelper | null = null;

/**
 * `$device` is an Options API global property, so a setup-mode caller cannot read it. The helper
 * behind it only reads the platform for `getSystemKey()`, so one shared instance answers for every
 * shortcut this composable registers.
 */
function systemKey(): string {
    deviceHelper ??= new Shopware.Helper.DeviceHelper() as DeviceHelper;

    return deviceHelper.getSystemKey();
}

/** @private */
export interface UseShortcutOptions {
    /**
     * Whether the shortcut may fire, re-evaluated on every keystroke. The Options API equivalent was
     * `{ active, method }`, where `active` could also be a constant.
     */
    active?: (() => boolean) | boolean;
}

/**
 * Registers a keyboard shortcut for as long as the calling component is mounted.
 *
 * Setup-mode equivalent of the `shortcuts` option, which named its handler as a string and resolved
 * it off the instance — something a `<script setup>` component cannot answer. The handler is passed
 * as a function instead. Both go through the same registry, so an option-based and a composable
 * shortcut compete for the same key on equal terms: the first registration of a key wins, which is
 * the precedence the option always had.
 *
 * @private
 */
export default function useShortcut(key: string, handler: () => void, options: UseShortcutOptions = {}): void {
    const instance = getCurrentInstance();

    if (!instance) {
        Shopware.Utils.debug.warn('useShortcut', 'Must be called during setup, so the shortcut can be unregistered.');

        return;
    }

    const active = options.active ?? true;

    const unregister = registerShortcut({
        key,
        handler,
        systemKey,
        active: typeof active === 'boolean' ? () => active : active,
    });

    onScopeDispose(unregister);
}
