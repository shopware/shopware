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
            FeatureConfig.addFlag(flagName);

            if (isActive) {
                FeatureConfig.activate(flagName);
            }
        });
    }

    static getAll() {
        return this.flags;
    }

    static addFlag(flagName) {
        this.flags[flagName] = false;
    }

    static activate(flagName) {
        this.flags[flagName] = true;
    }

    static isActive(flagName) {
        if (!this.flags.hasOwnProperty(flagName)) {
            throw new Error(`Unable to retrieve flag ${flagName}, not registered`);
        }

        return this.flags[flagName];
    }
}
