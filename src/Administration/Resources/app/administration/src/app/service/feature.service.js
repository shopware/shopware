/**
 * @module app/feature-service
 */

/**
 * A service for Feature flags
 */
export default class FeatureService {
    constructor(Feature) {
        this.Feature = Feature;
    }

    isActive(flagName) {
        return this.Feature.isActive(flagName);
    }
}
