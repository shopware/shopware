/**
 * @sw-package framework
 *
 * @module core/feature-config
 */

import { warn } from 'src/core/service/utils/debug.utils';

/**
 * A static registry containing a list of all registered flags and the associated activation state
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class Feature {
    static flags: { [featureName: string]: boolean } = {};

    static init(flagConfig: { [featureName: string]: boolean }): void {
        Object.entries(flagConfig).forEach(([flagName, isActive]) => {
            this.flags[flagName.toUpperCase()] = isActive;
        });
    }

    static getAll(): { [featureName: string]: boolean } {
        return this.flags;
    }

    static isActive(flagName: string): boolean {
        flagName = flagName.toUpperCase();

        if (!this.flags.hasOwnProperty(flagName)) {
            // if not set, its false
            return false;
        }

        return this.flags[flagName];
    }

    /**
     * Guards a deprecated API where it is used, like the PHP `Feature::triggerDeprecationOrThrow()`:
     * warns while `majorFlag` is inactive, throws once it is active.
     *
     * @example
     * Shopware.Feature.triggerDeprecationOrThrow('V6_8_0_0', 'oldMethod() is deprecated. Use newMethod() instead.');
     */
    static triggerDeprecationOrThrow(majorFlag: string, message: string): void {
        reportDeprecation(this.isActive(majorFlag), message);
    }
}

const warnedDeprecations = new Set<string>();

/**
 * @private
 */
export function reportDeprecation(isMajorActive: boolean, message: string): void {
    if (isMajorActive) {
        throw new Error(`Tried to access deprecated functionality: ${message}`);
    }

    // A deprecated computed property is read on every render, so warn only once per message.
    if (!warnedDeprecations.has(message)) {
        warnedDeprecations.add(message);
        warn('Deprecation', message);
    }
}
