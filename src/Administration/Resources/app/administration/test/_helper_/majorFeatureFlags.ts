/**
 * @sw-package framework
 * @private
 */

type FeatureConfig = {
    shopware?: {
        feature?: {
            flags?: Array<{
                name: string;
                major?: boolean;
            }>;
        };
    };
};

/**
 * @private
 */
export default function getMajorFeatureFlags(config: FeatureConfig): string[] {
    return (config.shopware?.feature?.flags ?? [])
        .filter(({ major }) => major)
        .map(({ name }) => name.toUpperCase().replace(/[.:-]/g, '_'));
}
