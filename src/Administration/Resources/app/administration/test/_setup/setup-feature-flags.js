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

// FEATURE_ALL is `major` or a single major (`v6.8.0.0`), the lanes integration-major.yml runs.
global.activeFeatureFlags = getMajorFeatureFlags(featureConfig, process.env.FEATURE_ALL ?? '');

// Registers every flag in both spellings, like `window._features_` in the browser, so a guard with an
// unknown flag is reported
const flagNames = featureConfig.shopware.feature.flags.flatMap(({ name }) => [name, normalizeFeatureFlag(name)]);
Feature.init(Object.fromEntries(flagNames.map((name) => [name, false])));
