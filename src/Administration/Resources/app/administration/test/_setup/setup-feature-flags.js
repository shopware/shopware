/**
 * @sw-package framework
 */

import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { parse } from 'yaml';

import getMajorFeatureFlags from '../_helper_/majorFeatureFlags';

const featureConfigPath = resolve(
    process.env.ADMIN_PATH,
    '../../../../Core/Framework/Resources/config/packages/feature.yaml',
);

global.activeFeatureFlags =
    process.env.FEATURE_ALL === 'major' ? getMajorFeatureFlags(parse(readFileSync(featureConfigPath, 'utf8'))) : [];
