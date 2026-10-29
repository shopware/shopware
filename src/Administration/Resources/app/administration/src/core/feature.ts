/**
 * @sw-package framework
 *
 * @module core/feature-config
 */

import { error, warn } from 'src/core/service/utils/debug.utils';
import deprecationBaseline from 'src/core/deprecation-baseline.json';

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
     * `'author'`: the template that renders the deprecated component or passes the deprecated prop. Passed by the
     * deprecation plugin and by wrapper components that guard their own old usage.
     */
    _blame?: 'author' | 'caller';
};

/**
 * The key of a deprecation in `src/core/deprecation-baseline.json`: the message without the component path the
 * deprecation plugin appends (`Used in: …`), so one key covers every place the deprecated API is used.
 *
 * @private
 * @example
 * deprecationBaselineKey('The component "sw-x" is deprecated.\nUsed in: sw-page > sw-x'); // 'The component "sw-x" is deprecated.'
 */
export function deprecationBaselineKey(message: string): string {
    return message.split('\nUsed in: ')[0];
}

/**
 * Whether `message` is in `src/core/deprecation-baseline.json`, the known core debt that logs an error instead of
 * throwing once its major is active. Matched per message, as the running Administration does not know the usage site.
 *
 * @example
 * isBaselined('The component "sw-x" is deprecated.\nUsed in: sw-page > sw-x'); // true when the baseline lists its key
 */
function isBaselined(message: string): boolean {
    return Object.hasOwn(deprecationBaseline, deprecationBaselineKey(message));
}

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
     * warns while `majorFlag` is inactive, throws once it is active. A message listed in
     * `src/core/deprecation-baseline.json` is known core debt, so it logs an error instead of throwing.
     * An unregistered flag can never become active, so it logs an error instead of the warning, unless it is
     * an upcoming major the backend does not register yet.
     *
     * @example
     * Shopware.Feature.triggerDeprecationOrThrow('V6_8_0_0', 'oldMethod() is deprecated. Use newMethod() instead.');
     */
    // eslint-disable-next-line @typescript-eslint/no-unused-vars -- only the Jest deprecation recorder reads `options`
    static triggerDeprecationOrThrow(majorFlag: string, message: string, options?: DeprecationOptions): void {
        const isMajorActive = this.isActive(majorFlag);

        if (isMajorActive && !isBaselined(message)) {
            throw new Error(`Tried to access deprecated functionality: ${message}`);
        }

        // A deprecated computed property is read on every render, so report only once per message.
        if (this.reportedDeprecations.has(message)) {
            return;
        }

        this.reportedDeprecations.add(message);

        if (isMajorActive) {
            // eslint-disable-next-line sw-deprecation-rules/no-manual-deprecation-notices -- this is the guard's own emitter
            error(
                'Deprecation',
                `${message}\nThis deprecation is in the core deprecation baseline, so it does not throw yet. Migrate your usage before the major: it will throw then.`,
            );
        } else if (this.isKnownMajor(majorFlag)) {
            // eslint-disable-next-line sw-deprecation-rules/no-manual-deprecation-notices -- this is the guard's own emitter
            warn('Deprecation', message);
        } else {
            // eslint-disable-next-line sw-deprecation-rules/no-manual-deprecation-notices -- this is the guard's own emitter
            error(
                'Deprecation',
                `${message}\nNote: this deprecation has a typo: "${majorFlag}" is an unknown feature flag. Please fix it.`,
            );
        }
    }
}
