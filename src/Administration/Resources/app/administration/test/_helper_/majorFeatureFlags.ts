/**
 * @sw-package framework
 * @private
 */

type FeatureFlag = {
    name: string;
    major?: boolean;
    majorVersion?: string;
};

type FeatureConfig = {
    shopware?: {
        feature?: {
            flags?: Array<FeatureFlag>;
        };
    };
};

const ALL_MAJOR = 'major';

function normalizeName(name: string): string {
    return name.toUpperCase().replace(/[.:-]/g, '_');
}

/**
 * Turns a version-shaped flag name or FEATURE_ALL value (`v6.8.0.0`, `V6_8_0_0`) into comparable
 * segments, or null when it is not version-shaped (`major`, `minor`, `1`, ...).
 */
function majorVersion(value: string): number[] | null {
    const match = /^V?(\d+(?:_\d+){1,3})$/.exec(normalizeName(value));

    return match ? match[1].split('_').map(Number) : null;
}

function arrivesUpTo(flag: FeatureFlag, target: number[]): boolean {
    // The major a flag arrives in is either encoded in its name or declared via `majorVersion`.
    const arrivesIn = majorVersion(flag.name) ?? (flag.majorVersion ? majorVersion(flag.majorVersion) : null);

    // A major flag that names no target major belongs to every major, so it stays on in all of them.
    if (arrivesIn === null) {
        return true;
    }

    for (let i = 0; i < Math.max(arrivesIn.length, target.length); i += 1) {
        const arrives = arrivesIn[i] ?? 0;
        const targeted = target[i] ?? 0;

        if (arrives !== targeted) {
            return arrives < targeted;
        }
    }

    return true;
}

/**
 * The major flags a `FEATURE_ALL` value activates: all of them for `major`, and the ones arriving up
 * to and including the given major for a version (`v6.8.0.0`). Any other value is not a major run
 * and activates none — mirrors `Feature::isActive()` in PHP.
 *
 * @private
 */
export default function getMajorFeatureFlags(config: FeatureConfig, featureAll: string = ALL_MAJOR): string[] {
    const target = majorVersion(featureAll);

    if (featureAll !== ALL_MAJOR && target === null) {
        return [];
    }

    return (config.shopware?.feature?.flags ?? [])
        .filter((flag) => flag.major)
        .filter((flag) => target === null || arrivesUpTo(flag, target))
        .map(({ name }) => normalizeName(name));
}
