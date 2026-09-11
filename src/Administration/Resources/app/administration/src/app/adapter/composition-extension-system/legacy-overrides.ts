/**
 * @sw-package framework
 * @private
 *
 * Converts factory registrations once, retaining their identity and configured order across mounts.
 * Synchronous configurations are available during setup. Async configurations share one resolution.
 */
import type { ComponentConfig } from 'src/core/factory/async-component.factory';
import { shouldActivateShim, convertOptionsApiOverrideToCompositionApi } from '../options-composition-shim';
import type { OverrideFn } from '../options-composition-shim';

/** @private */
export type Registration = {
    config: () => Promise<ComponentConfig | boolean>;
    resolvedConfig?: ComponentConfig;
};

const conversions = new WeakMap<Registration, OverrideFn | null>();
const resolutions = new WeakMap<Registration, Promise<ComponentConfig | boolean>>();

/** @private */
export function synchronizeLegacyOverrides(
    name: string,
    overrides: OverrideFn[],
    registrations: Registration[] = Shopware.Component.getOverrideRegistry?.().get(name) ?? [],
): void {
    const pending: Promise<ComponentConfig | boolean>[] = [];

    for (const registration of registrations) {
        if (registration.resolvedConfig && pending.length === 0) {
            appendConversion(name, registration, registration.resolvedConfig, overrides);
            continue;
        }
        let resolution = resolutions.get(registration);
        if (!resolution) {
            resolution = registration.config();
            resolutions.set(registration, resolution);
        }
        pending.push(resolution);
    }

    if (!pending.length) return;
    const unresolved = registrations.slice(registrations.length - pending.length);
    void Promise.all(pending)
        .then((configs) => {
            configs.forEach((config, index) => appendConversion(name, unresolved[index], config, overrides));
        })
        .catch((error: unknown) => {
            unresolved.forEach((entry) => resolutions.delete(entry));
            console.error(`[Options API Shim] Failed to resolve overrides for "${name}":`, error);
        });
}

function appendConversion(
    name: string,
    registration: Registration,
    config: ComponentConfig | boolean,
    overrides: OverrideFn[],
): void {
    if (!conversions.has(registration)) {
        conversions.set(
            registration,
            typeof config !== 'boolean' && shouldActivateShim(config)
                ? convertOptionsApiOverrideToCompositionApi(name, config)
                : null,
        );
    }
    const converted = conversions.get(registration);
    if (converted && !overrides.includes(converted)) overrides.push(converted);
}
