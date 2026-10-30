/**
 * @sw-package framework
 */
import * as fs from 'fs';
import * as path from 'path';
import { globSync } from 'glob';
// eslint-disable-next-line sw-core-rules/mini-module-boundaries -- the check is Administration-internal and only meta tests need it
import { findMissingPublicNames } from 'src/core/public-component/private';

/** The index.ts of every mini-module that declares a component. */
// eslint-disable-next-line no-undef -- adminPath is a global of the Jest environment
const declarations = globSync(path.join(adminPath, 'src/**/index.ts')).filter((file) =>
    fs.readFileSync(file, 'utf-8').includes('definePublicComponent('),
);

describe('public components', () => {
    it('list only names their component has', async () => {
        const missing = await Promise.all(
            declarations.map(async (file) => {
                const { default: declaration } = await import(file);
                const loaded = await declaration.load();
                const component = loaded.default ?? loaded;

                return findMissingPublicNames(declaration.public ?? {}, component).map(
                    (name) => `${declaration.name} ${name}`,
                );
            }),
        );

        expect(missing.flat()).toEqual([]);
    });
});
