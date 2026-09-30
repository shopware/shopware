/**
 * @sw-package framework
 * @private
 *
 * Imported by `src/index.ts`, so the composables exist before login overrides and `src/app/main` run.
 * `src/core/shopware.ts` can't import them: Jest loads it before a spec's `jest.mock` calls apply.
 */
import { ShopwareInstance } from 'src/core/shopware';
import composables from './index';

ShopwareInstance.Composables = composables;
