/**
 * @package storefront
 */
class FeatureSingleton {

    constructor() {
        this.flags = {};
        if (window.features) {
            this.init(window.features);
        }
    }

    /**
     * init the feature Flags
     *
     * @param {Object} flagConfig
     *
     */
    init(flagConfig) {
        Object.entries(flagConfig).forEach(([flagName, isActive]) => {
            this.flags[flagName] = isActive;
        });
    }

    /**
     * checks if a feature flag is active
     *
     * @param {string} flagName
     *
     * @returns {boolean}
     */
    isActive(flagName) {
        if (!Object.prototype.hasOwnProperty.call(this.flags, flagName)) {
            // if not set, its false
            return false;
        }

        return this.flags[flagName];
    }
}
/**
 * Create the Feature instance.
 * @type {Readonly<FeatureSingleton>}
 */
export const FeatureInstance = Object.freeze(new FeatureSingleton());


export default class Feature {

    constructor() {
        window.Feature = this;
    }

    /**
     * init the feature Flags
     *
     * @param {Object} flagConfig
     *
     */
    static init(flagConfig = {}) {
        FeatureInstance.init(flagConfig);
    }

    /**
     * checks if a feature flag is active
     *
     * @param {string} flagName
     *
     * @returns {boolean}
     */
    static isActive(flag) {
        return FeatureInstance.isActive(flag);
    }
}
