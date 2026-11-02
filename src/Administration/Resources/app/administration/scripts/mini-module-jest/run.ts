/**
 * @sw-package framework
 * @private
 *
 * Runs the tests of mini-modules: npm run unit:mini-module -- [<module directory>] [--contracts]
 */

import { spawnSync } from 'node:child_process';
import { buildJestArguments } from './arguments';

const options = process.argv.slice(2);
const moduleDirectory = options.find((option) => !option.startsWith('--')) ?? null;
const jestArguments = buildJestArguments(moduleDirectory, options.includes('--contracts'));

const { status } = spawnSync('jest', ['--config', 'jest.config.js', ...jestArguments], { stdio: 'inherit', shell: false });

process.exit(status ?? 1);
