import type { default as FeatureType, DeprecationOptions } from 'src/core/feature';

/**
 * @sw-package framework
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

    isActive(flagName: string): boolean {
        return this.Feature.isActive(flagName);
    }

    isRegistered(flagName: string): boolean {
        return this.Feature.isRegistered(flagName);
    }

    triggerDeprecationOrThrow(majorFlag: string, message: string, options?: DeprecationOptions): void {
        this.Feature.triggerDeprecationOrThrow(majorFlag, message, options);
    }
}
