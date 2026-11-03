/**
 * @sw-package framework
 * @private
 */

import fs from 'node:fs';
import path from 'node:path';

/** Whether a directory is a mini-module: an `index.ts` or a `private.ts`, a `src/` and a `tests/`. */
export const isMiniModuleDirectory = (directory: string) =>
    ['index.ts', 'private.ts'].some((entry) => fs.existsSync(path.join(directory, entry))) &&
    ['src', 'tests'].every((folder) => fs.existsSync(path.join(directory, folder)));

/**
 * The files and folders of a component directory that belong to the component: not a mini-module inside it, such as
 * a child the module index registers through its register.ts, which stays where it is with its own index and tests.
 */
export const ownEntries = (componentDirectory: string) =>
    fs.readdirSync(componentDirectory).filter((file) => !isMiniModuleDirectory(path.join(componentDirectory, file)));
