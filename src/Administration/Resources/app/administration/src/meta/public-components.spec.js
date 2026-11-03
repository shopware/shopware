/**
 * @sw-package framework
 */
import * as fs from 'fs';
import * as path from 'path';
import { globSync } from 'glob';
// eslint-disable-next-line sw-core-rules/mini-module-boundaries -- the check is Administration-internal and only meta tests need it
import { findMissingPublicNames } from 'src/core/public-component/private';
import componentImports from '../../test/_helper_/componentWrapper/component-imports';

/** The index.ts of every mini-module that declares a component, and the private.ts of every nested one. */
// eslint-disable-next-line no-undef -- adminPath is a global of the Jest environment
const declarations = globSync(path.join(adminPath, 'src/**/{index,private}.ts')).filter((file) =>
    /define(Public|Private)Component\(/.test(fs.readFileSync(file, 'utf-8')),
);

/**
 * The component a declared component extends, with the components that one extends in turn,
 * read through the Jest import map, so the check counts the members it inherits.
 */
async function loadBase(name) {
    if (!name) {
        return undefined;
    }

    const loaded = await import(componentImports[name].p);
    const base = loaded.default ?? loaded;

    return { ...base, extends: await loadBase(componentImports[name].en) };
}

describe('public components', () => {
    it('list only names their component has', async () => {
        const missing = await Promise.all(
            declarations.map(async (file) => {
                const { default: declaration } = await import(file);
                const loaded = await declaration.load();
                const component = loaded.default ?? loaded;

                // Vue names every <script setup> component; the setup transform checks those against index.ts.
                if (component.__name) {
                    return [];
                }

                const extended = { ...component, extends: await loadBase(declaration.extends) };

                return findMissingPublicNames(declaration.public ?? {}, extended).map(
                    // eslint-disable-next-line no-undef -- adminPath is a global of the Jest environment
                    (name) => `${declaration.name ?? path.relative(adminPath, file)} ${name}`,
                );
            }),
        );

        expect(missing.flat()).toEqual([]);
    });
});
