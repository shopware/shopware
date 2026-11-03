/**
 * @sw-package framework
 * @private
 */

function normalizeName(name) {
    return name.toUpperCase().replace(/[.:-]/g, '_');
}

function isMajorVersionFlag(name) {
    return /^V\d+_\d+_0_0$/.test(normalizeName(name));
}

function isTrue(value) {
    return Boolean(value) && value !== '0' && value !== 'false';
}

/**
 * Mirrors the PHP feature resolution for the Jest baseline in a major CI lane.
 *
 * @private
 */
export default function getMajorFeatureFlags(config, environment) {
    const flags = config.shopware?.feature?.flags ?? [];

    const hasMajorLane = flags.some(
        (flag) => isMajorVersionFlag(flag.name) && isTrue(environment[normalizeName(flag.name)]),
    );

    const active = (flag) => {
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
            return parent !== undefined && isMajorVersionFlag(parent.name) && active(parent);
        }

        return flag.default === true;
    };

    if (isTrue(environment.FEATURE_ALL)) {
        return flags.filter(active).map(({ name }) => normalizeName(name));
    }

    if (!hasMajorLane) {
        return flags.filter((flag) => isTrue(environment[normalizeName(flag.name)])).map(({ name }) => normalizeName(name));
    }

    return flags.filter(active).map(({ name }) => normalizeName(name));
}
