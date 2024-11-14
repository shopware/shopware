/**
 * @package admin
 *
 * @module core/feature-config
 */

/**
 * A static registry containing a list of all registered flags and the associated activation state
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class Feature {
    static flags: { [featureName: string]: boolean } = {};

    static init(flagConfig: { [featureName: string]: boolean }): void {
        Object.entries(flagConfig).forEach(
            ([
                flagName,
                isActive,
            ]) => {
                this.flags[flagName.toUpperCase()] = isActive;
            },
        );
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
}
