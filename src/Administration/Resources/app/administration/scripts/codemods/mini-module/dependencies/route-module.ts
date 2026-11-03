/**
 * @sw-package framework
 * @private
 */

import fs from 'node:fs';
import path from 'node:path';
import type { AdminIndex, FileRewrite, OutsideEdit, UseRewrite } from './model';
import { renderDependencies } from './render-dependencies';
import { DependencyRegistry, routeFile } from './route-file';
import { findLeftovers } from './leftovers';
import { applyOutsideEdits } from './outside-edits';
import { rewriteComponentHelper } from './rewrites/component-helper';
import { rewriteContext } from './rewrites/context';
import { rewriteFeature } from './rewrites/feature';
import { rewriteFilter } from './rewrites/filter';
import { rewriteGlobalPath } from './rewrites/global-path';
import { rewriteInject } from './rewrites/inject';
import { rewriteMixin } from './rewrites/mixin';
import { rewriteOutsideImports } from './rewrites/outside-import';
import { rewriteService } from './rewrites/service';
import { rewriteStore } from './rewrites/store';
import { rewriteWrapComponentConfig } from './rewrites/wrap-component-config';

/** The rewrites of `Shopware` uses, tried in this order until one takes a use. */
export const USE_REWRITES: UseRewrite[] = [
    rewriteWrapComponentConfig,
    rewriteComponentHelper,
    rewriteMixin,
    rewriteStore,
    rewriteFilter,
    rewriteService,
    rewriteContext,
    rewriteFeature,
    rewriteGlobalPath,
];

/** The rewrites of whole files, after the uses. */
export const FILE_REWRITES: FileRewrite[] = [rewriteInject, rewriteOutsideImports];

/** Whether a directory is a mini-module of its own, whose `src/` routes through its own dependencies.ts. */
const isMiniModule = (directory: string) =>
    ['index.ts', 'private.ts'].some((entry) => fs.existsSync(path.join(directory, entry))) &&
    ['src', 'tests'].every((folder) => fs.existsSync(path.join(directory, folder)));

/** The script files of a module's `src/`, without specs and without nested mini-modules. */
const sourceFiles = (directory: string): string[] =>
    fs.readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
        const file = path.join(directory, entry.name);
        if (entry.isDirectory()) {
            return isMiniModule(file) ? [] : sourceFiles(file);
        }

        return /\.[jt]s$/.test(entry.name) && !/\.(spec|d)\.[jt]s$/.test(entry.name) ? [file] : [];
    });

/** What routing a module did: the files it changed, inside and outside the module, and what is left for the sweep. */
export type RoutedModule = {
    changed: string[];
    outside: OutsideEdit[];
    outsideFiles: string[];
    leftovers: string[];
};

/**
 * Writes the module's dependencies.ts and routes every file of its `src/` through it, then makes the edits
 * outside the module that this needs: new service name constants, exported store ids and filter names.
 * Paths in the result are relative to the Administration root; leftovers read `src/<file>:<line>: <code>`.
 */
export function routeModule(options: {
    adminRoot: string;
    moduleRoot: string;
    name: string;
    packageName: string;
    index: AdminIndex;
}): RoutedModule {
    const { adminRoot, moduleRoot, name, packageName, index } = options;
    const relative = (file: string) => path.relative(adminRoot, file).split(path.sep).join('/');
    const dependenciesFile = path.join(moduleRoot, 'dependencies.ts');
    if (fs.existsSync(dependenciesFile)) {
        throw new Error(`${relative(dependenciesFile)} exists already, so the codemod does not route src/ again.`);
    }

    const registry = new DependencyRegistry();
    const changed: string[] = [];
    const outside: OutsideEdit[] = [];
    const leftovers: string[] = [];

    for (const file of sourceFiles(path.join(moduleRoot, 'src'))) {
        const code = fs.readFileSync(file, 'utf8');
        const routed = routeFile(code, {
            fileName: file,
            moduleRoot,
            index,
            registry,
            useRewrites: USE_REWRITES,
            fileRewrites: FILE_REWRITES,
        });

        if (routed.code !== code) {
            fs.writeFileSync(file, routed.code);
            changed.push(relative(file));
        }

        outside.push(...routed.outsideEdits);
        const inModule = path.relative(moduleRoot, file).split(path.sep).join('/');
        leftovers.push(...findLeftovers(routed.code, file, moduleRoot).map((line) => `${inModule}:${line}`));
    }

    if (registry.all.length > 0) {
        fs.writeFileSync(dependenciesFile, renderDependencies(packageName, name, registry.all));
        changed.push(relative(dependenciesFile));
    }

    return { changed, outside, outsideFiles: applyOutsideEdits(adminRoot, outside), leftovers };
}
