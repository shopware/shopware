/**
 * @sw-package framework
 */

import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { parse } from 'yaml';

import Feature from 'src/core/feature';
import getMajorFeatureFlags from '../_helper_/majorFeatureFlags';
import normalizeFeatureFlag from '../_helper_/normalizeFeatureFlag';

const featureConfigPath = resolve(
    process.env.ADMIN_PATH,
    '../../../../Core/Framework/Resources/config/packages/feature.yaml',
);

const featureConfig = parse(readFileSync(featureConfigPath, 'utf8'));
const { flags } = featureConfig.shopware.feature;

// Like in the browser, the flags that are on by default are active. FEATURE_ALL adds the majors of the
// lanes integration-major.yml runs: all of them for `major`, or those up to a single major like `v6.8.0.0`.
const defaultFlags = flags.filter((flag) => flag.default).map(({ name }) => normalizeFeatureFlag(name));
const majorFlags = getMajorFeatureFlags(featureConfig, process.env.FEATURE_ALL ?? '');
global.activeFeatureFlags = [...new Set([...defaultFlags, ...majorFlags])];

// Registers every flag in both spellings, like `window._features_` in the browser, so a guard with an
// unknown flag is reported. Flags a test activates later only reach `FeatureMock.isActive`.
const activeFlags = new Set(global.activeFeatureFlags);
const flagNames = flags.flatMap(({ name }) => [name, normalizeFeatureFlag(name)]);
Feature.init(Object.fromEntries(flagNames.map((name) => [name, activeFlags.has(normalizeFeatureFlag(name))])));
