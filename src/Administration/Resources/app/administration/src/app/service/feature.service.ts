// eslint-disable-next-line import/no-named-default
import type { default as FeatureType } from 'src/core/feature';

/**
 * @package admin
 *
 * @module app/feature-service
 */

/**
 * A service for Feature flags
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class FeatureService {
    private Feature: typeof FeatureType;

    constructor(Feature: typeof FeatureType) {
        this.Feature = Feature;
    }

    isActive(flagName: string):boolean {
        return this.Feature.isActive(flagName);
    }
}
