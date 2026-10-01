/**
 * @sw-package framework
 *
 * @module core/feature-config
 */

import { error, warn } from 'src/core/service/utils/debug.utils';

/**
 * A static registry containing a list of all registered flags and the associated activation state
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class Feature {
    static flags: { [featureName: string]: boolean } = {};

    private static reportedDeprecations = new Set<string>();

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
     * Whether the flag exists at all, active or not.
     */
    static isRegistered(flagName: string): boolean {
        return this.flags.hasOwnProperty(flagName.toUpperCase());
    }

    /**
     * Guards a deprecated API where it is used, like the PHP `Feature::triggerDeprecationOrThrow()`:
     * warns while `majorFlag` is inactive, throws once it is active. An unregistered flag can never
     * become active, so it logs an error instead.
     *
     * @example
     * Shopware.Feature.triggerDeprecationOrThrow('V6_8_0_0', 'oldMethod() is deprecated. Use newMethod() instead.');
     */
    static triggerDeprecationOrThrow(majorFlag: string, message: string): void {
        if (this.isActive(majorFlag)) {
            throw new Error(`Tried to access deprecated functionality: ${message}`);
        }

        // A deprecated computed property is read on every render, so report only once per message.
        if (this.reportedDeprecations.has(message)) {
            return;
        }

        this.reportedDeprecations.add(message);

        if (this.isRegistered(majorFlag)) {
            warn('Deprecation', message);
        } else {
            error(
                'Deprecation',
                `${message}\nNote: this deprecation has a typo: "${majorFlag}" is an unknown feature flag. Please fix it.`,
            );
        }
    }
}
