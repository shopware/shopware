/**
 * @sw-package framework
 * @private
 */
import { existsSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { parse } from 'yaml';

// Not `ADMIN_PATH`: an exported shell value may point it at another checkout.
// The Core bundle sits in `src/Core` in a platform checkout and in `vendor/shopware/core` in a Composer install.
const featureRegistryCandidates = ['Core', 'core'].map((coreFolder) =>
    resolve(__dirname, '../..', '../../../..', coreFolder, 'Framework/Resources/config/packages/feature.yaml'),
);

type FeatureFlag = {
    name: string;
    major?: string;
    default?: boolean;
};

/**
 * The part of `feature.yaml` the major flag resolution reads.
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
 * Loads the feature registry from `feature.yaml`, the file the PHP side resolves the flags from.
 * Use it in Jest helpers that need the flag definitions; it reads the file on each call.
 *
 * @private
 * @example
 * isMajorRun(loadFeatureConfig(), process.env);
 */
export function loadFeatureConfig(): FeatureConfig {
    const featureRegistryPath = featureRegistryCandidates.find((candidate) => existsSync(candidate));

    if (!featureRegistryPath) {
        throw new Error(`Cannot find the feature flag config, looked at:\n${featureRegistryCandidates.join('\n')}`);
    }

    return parse(readFileSync(featureRegistryPath, 'utf8')) as FeatureConfig;
}

function normalizeName(name: string): string {
    return name.toUpperCase().replace(/[.:-]/g, '_');
}

function isMajorVersionFlag(name: string): boolean {
    return /^V\d+_\d+_0_0$/.test(normalizeName(name));
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
        (flag) => isMajorVersionFlag(flag.name) && isTrue(environment[normalizeName(flag.name)]),
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

/**
 * Whether a Jest run with `environment` activates a major, like `composer admin:unit:major` does with
 * `V6_8_0_0=1`. The flags on by default, like `V6_7_0_0`, do not make a major run.
 *
 * @private
 * @example
 * isMajorRun(featureConfig, { V6_8_0_0: '1' }); // true
 */
export function isMajorRun(config: FeatureConfig, environment: Record<string, string | undefined>): boolean {
    return getMajorFeatureFlags(config, environment).some(isMajorVersionFlag);
}
