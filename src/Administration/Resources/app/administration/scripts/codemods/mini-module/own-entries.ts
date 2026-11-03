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
 * The files below a directory that belong to the component, relative to it: none of a mini-module at any depth,
 * such as a child the module index registers through its register.ts, which stays where it is with its own index and tests.
 *
 * @example
 * // sw-x/index.js, sw-x/components/sw-x-child/{private.ts,src/,tests/}, sw-x/components/sw-x-modal/index.js
 * ownFiles('sw-x'); // → ['index.js', 'components/sw-x-modal/index.js']
 */
export const ownFiles = (directory: string): string[] =>
    fs.readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
        const file = path.join(directory, entry.name);
        if (!entry.isDirectory()) {
            return [entry.name];
        }

        return isMiniModuleDirectory(file) ? [] : ownFiles(file).map((inner) => `${entry.name}/${inner}`);
    });

/**
 * The files and folders of a component directory that belong to the component, without the mini-modules in it.
 * A folder that holds a mini-module deeper down, such as `components/` with a migrated child, is listed with
 * `holdsModule`, so the caller moves its own files one by one and leaves the module in place.
 */
export const ownEntries = (componentDirectory: string) =>
    fs
        .readdirSync(componentDirectory)
        .filter((file) => !isMiniModuleDirectory(path.join(componentDirectory, file)))
        .map((name) => {
            const file = path.join(componentDirectory, name);
            const isDirectory = fs.statSync(file).isDirectory();
            const holdsModule =
                isDirectory &&
                fs
                    .readdirSync(file, { recursive: true, encoding: 'utf8' })
                    .some((inner) => isMiniModuleDirectory(path.join(file, inner)));

            return { name, holdsModule };
        });
