/**
 * @sw-package framework
 * @private
 *
 * The feature flags of `feature.yaml`, the registry the PHP side resolves the flags from. The Jest setup and the
 * `scripts/generate-*` generators read the flags through this file, so they agree on names and versions.
 */
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { parse } from 'yaml';

// Not `ADMIN_PATH`: an exported shell value may point it at another checkout.
const featureRegistryPath = resolve(__dirname, '..', '../../../../Core/Framework/Resources/config/packages/feature.yaml');

const VERSION_FLAG = /^V(\d+)_(\d+)_0_0$/;

/** @private */
export type FeatureFlag = {
    name: string;
    major?: string;
    default?: boolean;
};

/**
 * The part of `feature.yaml` that the Administration reads.
 *
 * @private
 * @example
 * const config: FeatureConfig = { shopware: { feature: { flags: [{ name: 'V6_8_0_0' }] } } };
 */
export type FeatureConfig = {
    shopware?: {
        feature?: {
            flags?: Array<FeatureFlag>;
        };
    };
};

/**
 * Loads the feature registry from `feature.yaml`. It reads the file on each call.
 *
 * @private
 */
export function loadFeatureConfig(): FeatureConfig {
    return parse(readFileSync(featureRegistryPath, 'utf8')) as FeatureConfig;
}

/**
 * Brings a feature flag into the upper snake case form the runtime uses, so the same flag written in
 * either notation compares equal.
 *
 * Flags reach us in both spellings: `window._features_` and `.env` use `V6_8_0_0`, while
 * most `isActive()` calls use `v6.8.0.0`.
 *
 * ```
 * normalizeFeatureFlag('v6.8.0.0');              // 'V6_8_0_0'
 * normalizeFeatureFlag('V6_8_0_0');              // 'V6_8_0_0'  (already normalized)
 * normalizeFeatureFlag('FEATURE_NEXT_12345');    // 'FEATURE_NEXT_12345'
 * ```
 *
 * @private
 */
export function normalizeFeatureFlag(featureFlag: string): string {
    return featureFlag.toUpperCase().replace(/[.:-]/g, '_');
}

/** @private Whether two spellings name the same flag, such as `v6.8.0.0` and `V6_8_0_0`. */
export function isSameFlag(a: string, b: string): boolean {
    return normalizeFeatureFlag(a) === normalizeFeatureFlag(b);
}

/** @private */
export function flagsOf(config: FeatureConfig): FeatureFlag[] {
    return config.shopware?.feature?.flags ?? [];
}

/**
 * @private
 *
 * The major and minor number of a version flag such as `v6.8.0.0`, or `null` for any other flag.
 */
export function versionOfFlag(flag: string): [major: number, minor: number] | null {
    const [, major, minor] = VERSION_FLAG.exec(normalizeFeatureFlag(flag)) ?? [];

    return major === undefined ? null : [Number(major), Number(minor)];
}

/**
 * @private
 *
 * The flags that are on in the state of the next major: the version flag of the next major, which is the lowest
 * one that is still off, and every flag that names it as its `major`.
 */
export function majorFlagsOf(config: FeatureConfig): string[] {
    const flags = flagsOf(config);
    const [nextMajor] = flags
        .filter((flag) => flag.default !== true)
        .flatMap(({ name }) => {
            const version = versionOfFlag(name);

            return version ? [{ name, version }] : [];
        })
        .sort((a, b) => a.version[0] - b.version[0] || a.version[1] - b.version[1]);

    if (nextMajor === undefined) {
        throw new Error('feature.yaml has no version flag that is still off, so the next major is unknown.');
    }

    return [
        nextMajor.name,
        ...flags.filter(({ major }) => major !== undefined && isSameFlag(major, nextMajor.name)).map(({ name }) => name),
    ];
}

function isVersionFlag(flag: string): boolean {
    return versionOfFlag(flag) !== null;
}

function isTrue(value: string | undefined): boolean {
    return Boolean(value) && value !== '0' && value !== 'false';
}

/**
 * Mirrors the PHP feature resolution for the Jest baseline in a major CI lane.
 *
 * @private
 */
export function getMajorFeatureFlags(config: FeatureConfig, environment: Record<string, string | undefined>): string[] {
    const flags = flagsOf(config);
    const isSet = (flag: string) => isTrue(environment[normalizeFeatureFlag(flag)]);

    const active = (flag: FeatureFlag): boolean => {
        const explicit = environment[normalizeFeatureFlag(flag.name)];
        if (explicit !== undefined) {
            return isTrue(explicit);
        }

        if (isTrue(environment.FEATURE_ALL)) {
            return true;
        }

        const { major } = flag;
        if (typeof major === 'string') {
            const parent = flags.find(({ name }) => isSameFlag(name, major));
            return parent !== undefined && isVersionFlag(parent.name) && active(parent);
        }

        return flag.default === true;
    };

    const isMajorLane = isTrue(environment.FEATURE_ALL) || flags.some(({ name }) => isVersionFlag(name) && isSet(name));
    const enabled = isMajorLane ? flags.filter(active) : flags.filter(({ name }) => isSet(name));

    return enabled.map(({ name }) => normalizeFeatureFlag(name));
}

/**
 * Whether a Jest run with `environment` activates a major, like `composer admin:unit:major` does with
 * `V6_8_0_0=1`. The flags on by default, like `V6_7_0_0`, do not make a major run.
 *
 * @private
 * @example
 * isMajorRun(featureConfig, { V6_8_0_0: '1' }); // true
 */
export function isMajorRun(config: FeatureConfig, environment: Record<string, string | undefined>): boolean {
    return getMajorFeatureFlags(config, environment).some(isVersionFlag);
}
