/**
 * @sw-package framework
 */

import getMajorFeatureFlags, { loadFeatureConfig } from '../_helper_/majorFeatureFlags';
import normalizeFeatureFlag from '../_helper_/normalizeFeatureFlag';
import registerFeatureFlags from '../_helper_/registerFeatureFlags';

const featureConfig = loadFeatureConfig();
const { flags } = featureConfig.shopware.feature;

// Like in the browser, the flags that are on by default are active unless the environment switches them
// off. Major CI lanes set their version flag directly; FEATURE_ALL enables every flag.
const defaultFlags = flags
    .filter((flag) => flag.default && process.env[normalizeFeatureFlag(flag.name)] === undefined)
    .map(({ name }) => normalizeFeatureFlag(name));
const majorFlags = getMajorFeatureFlags(featureConfig, process.env);
global.activeFeatureFlags = [...new Set([...defaultFlags, ...majorFlags])];

// Flags a test activates later only reach `FeatureMock.isActive`.
registerFeatureFlags(global.activeFeatureFlags, featureConfig);
