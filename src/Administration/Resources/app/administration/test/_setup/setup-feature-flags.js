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

// FEATURE_ALL is `major` or a single major (`v6.8.0.0`), the lanes integration-major.yml runs.
global.activeFeatureFlags = getMajorFeatureFlags(
    parse(readFileSync(featureConfigPath, 'utf8')),
    process.env.FEATURE_ALL ?? '',
);
