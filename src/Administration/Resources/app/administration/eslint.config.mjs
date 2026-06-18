/**
 * @sw-package admin
 */

import { createJiti } from 'jiti';

const jiti = createJiti(import.meta.url);
const config = jiti('./eslint.config.ts');

export default config.default ?? config;
