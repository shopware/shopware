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
     * Guards a deprecated API at the boundary where it is consumed. Warns while `majorFlag` is
     * inactive and throws once it is active.
     *
     * Resolves the flag through `isActive` rather than delegating to the wrapped registry, so the
     * injected service and the Jest feature mock behave identically.
     *
     * @private
     */
    triggerDeprecationOrThrow(majorFlag: string, message: string): void {
        reportDeprecation(this.isActive(majorFlag), message);
    }
}
