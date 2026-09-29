import type { default as FeatureType } from 'src/core/feature';
import { reportDeprecation } from 'src/core/feature';

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

    /**
     * Same as `Feature.triggerDeprecationOrThrow()`. Resolves the flag through this service, so the flags
     * of the Jest feature mock apply.
     */
    triggerDeprecationOrThrow(majorFlag: string, message: string): void {
        reportDeprecation(this.isActive(majorFlag), message);
    }
}
