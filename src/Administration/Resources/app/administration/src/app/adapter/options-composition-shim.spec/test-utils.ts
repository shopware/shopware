/**
 * @sw-package framework
 */

/* eslint-disable @typescript-eslint/no-explicit-any */

import type { ComponentConfig } from 'src/core/factory/async-component.factory';
import { overrideComponentSetup } from 'src/app/adapter/composition-extension-system';
import type { OverrideFn } from 'src/app/adapter/options-composition-shim';
import { convertOptionsApiOverrideToCompositionApi } from 'src/app/adapter/options-composition-shim';

/** @private */
export { defineExtendable } from '../composition-extension-system/index.spec/test-utils';

/**
 * @private
 *
 * Converts an Options API override without its deprecation warning.
 */
export function convert(componentName: string, config: Record<string, any>): OverrideFn {
    const warn = jest.spyOn(console, 'warn').mockImplementation(() => {});
    const override = convertOptionsApiOverrideToCompositionApi(componentName, config as ComponentConfig);
    warn.mockRestore();

    return override;
}

/**
 * @private
 *
 * Registers a converted Options API override, so that it runs in the setup of every later instance.
 */
export function registerOptionsOverride(componentName: string, config: Record<string, any>): void {
    overrideComponentSetup()(componentName, convert(componentName, config));
}
