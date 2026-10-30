/**
 * @sw-package framework
 *
 * @private
 *
 * Runs the Vite CLI of the toolchain selected by the `ADMIN_VITE_8` feature flag, e.g. `npm run vite -- build`.
 *
 * @deprecated tag:v6.8.0 - Will be removed. The npm scripts call the Vite CLI directly again.
 */
import { spawnSync } from 'child_process';
import path from 'path';
import { getViteBinPath } from './index';

process.env.PROJECT_ROOT ||= path.resolve(__dirname, '../../../../../../../../');
process.env.VITE_CONFIG_NATIVE_IGNORE_WARNING ??= 'true';

const { status } = spawnSync(process.execPath, [getViteBinPath(), ...process.argv.slice(2)], { stdio: 'inherit' });

process.exit(status ?? 1);
