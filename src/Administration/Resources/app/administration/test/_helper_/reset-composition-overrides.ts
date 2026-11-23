/**
 * @sw-package framework
 * @private
 */

import { _overridesMap } from 'src/app/adapter/composition-extension-system';

/**
 * Clears every setup override registered with the composition extension system.
 *
 * Override SFCs and `overrideComponentSetup()` calls register into a module-level map that outlives a
 * single test, so a spec that registers one leaks it into the next. Call this from `beforeEach` to
 * start each test from an empty registry.
 *
 * @private
 */
export default function resetCompositionOverrides(): void {
    Object.keys(_overridesMap).forEach((name) => {
        delete _overridesMap[name];
    });
}
