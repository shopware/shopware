/**
 * @sw-package framework
 * @private
 */

type FeatureFlag = {
    name: string;
    major?: boolean | string;
    default?: boolean;
};

type FeatureConfig = {
    shopware?: {
        feature?: {
            flags?: Array<FeatureFlag>;
        };
    };
};

function normalizeName(name: string): string {
    return name.toUpperCase().replace(/[.:-]/g, '_');
}

function isTrue(value: string | undefined): boolean {
    return Boolean(value) && value !== '0' && value !== 'false';
}

/**
 * Mirrors the PHP feature resolution for the Jest baseline in a major CI lane.
 *
 * @private
 */
export default function getMajorFeatureFlags(
    config: FeatureConfig,
    environment: Record<string, string | undefined>,
): string[] {
    const flags = config.shopware?.feature?.flags ?? [];

    const hasMajorLane = flags.some(
        (flag) => /^v\d+\.\d+\.\d+\.\d+$/i.test(flag.name) && isTrue(environment[normalizeName(flag.name)]),
    );

    const active = (flag: FeatureFlag): boolean => {
        const explicit = environment[normalizeName(flag.name)];
        if (explicit !== undefined) {
            return isTrue(explicit);
        }

        if (isTrue(environment.FEATURE_ALL)) {
            return true;
        }

        if (typeof flag.major === 'string') {
            const parentName = normalizeName(flag.major);
            const parent = flags.find(({ name }) => normalizeName(name) === parentName);
            return parent?.major === true && active(parent);
        }

        return flag.default === true;
    };

    if (isTrue(environment.FEATURE_ALL)) {
        return flags.filter(active).map(({ name }) => normalizeName(name));
    }

    if (!hasMajorLane) {
        return flags.filter((flag) => isTrue(environment[normalizeName(flag.name)])).map(({ name }) => normalizeName(name));
    }

    return flags.filter((flag) => flag.major && active(flag)).map(({ name }) => normalizeName(name));
}
