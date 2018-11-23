/**
 * @module core/feature-config
 */

/**
 * A static registry containing a list of all registered flags and the associated activation state
 */
export default class FeatureConfig {
    static flags = {};

    static init(flagConfig) {
        Object.entries(flagConfig).forEach(([flagName, isActive]) => {
            this.flags[flagName] = isActive;
        });
    }

    static getAll() {
        return this.flags;
    }

    static isActive(flagName) {
        if (!this.flags.hasOwnProperty(flagName)) {
            throw new Error(`Unable to retrieve flag ${flagName}, not registered`);
        }

        return this.flags[flagName];
    }
}
