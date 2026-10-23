/**
 * @sw-package framework
 *
 * @module core/feature-config
 */

import { error, warn } from 'src/core/service/utils/debug.utils';

/**
 * Majors the Administration already deprecates for, while the backend does not register their flag yet.
 * `triggerDeprecationOrThrow` reports them as a regular deprecation instead of a typo.
 * Remove a version once it is registered in the backend `feature.yaml`.
 *
 * @private
 */
export const UNREGISTERED_UPCOMING_MAJORS = ['V6_9_0_0'];

/**
 * Who is at fault for a deprecation, read only by the Jest deprecation recorder to name the usage site.
 * Production ignores it.
 *
 * @private
 * @example
 * Shopware.Feature.triggerDeprecationOrThrow('V6_8_0_0', 'The component "sw-old" is deprecated.', { _blame: 'author' });
 */
export type DeprecationOptions = {
    /**
     * `'caller'` (default): the code that calls the deprecated API: the running component, else the calling file.
     * `'author'`: the template that renders the deprecated component or passes the deprecated prop. Only the
     * deprecation plugin passes it.
     */
    _blame?: 'author' | 'caller';
};

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
     * Whether `triggerDeprecationOrThrow` reports a deprecation for `majorFlag` as regular instead of as a typo:
     * the flag is registered, or an upcoming major the backend does not register yet.
     *
     * @private
     * @example
     * Feature.isKnownMajor('V6_9_0_0'); // true, upcoming
     */
    static isKnownMajor(majorFlag: string): boolean {
        return this.isRegistered(majorFlag) || UNREGISTERED_UPCOMING_MAJORS.includes(majorFlag.toUpperCase());
    }

    /**
     * Guards a deprecated API where it is used, like the PHP `Feature::triggerDeprecationOrThrow()`:
     * warns while `majorFlag` is inactive, throws once it is active. An unregistered flag can never
     * become active, so it logs an error instead, unless it is an upcoming major the backend does not register yet.
     *
     * @example
     * Shopware.Feature.triggerDeprecationOrThrow('V6_8_0_0', 'oldMethod() is deprecated. Use newMethod() instead.');
     */
    // eslint-disable-next-line @typescript-eslint/no-unused-vars -- only the Jest deprecation recorder reads `options`
    static triggerDeprecationOrThrow(majorFlag: string, message: string, options?: DeprecationOptions): void {
        if (this.isActive(majorFlag)) {
            throw new Error(`Tried to access deprecated functionality: ${message}`);
        }

        // A deprecated computed property is read on every render, so report only once per message.
        if (this.reportedDeprecations.has(message)) {
            return;
        }

        this.reportedDeprecations.add(message);

        if (this.isKnownMajor(majorFlag)) {
            warn('Deprecation', message);
        } else {
            error(
                'Deprecation',
                `${message}\nNote: this deprecation has a typo: "${majorFlag}" is an unknown feature flag. Please fix it.`,
            );
        }
    }
}
