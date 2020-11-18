/**
 * @module core/feature-config
 */

/**
 * A static registry containing a list of all registered flags and the associated activation state
 */
export default class Feature {
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
            // if not set, its false
            return false;
        }

        return this.flags[flagName];
    }
}
