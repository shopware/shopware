/**
 * @sw-package framework
 */

/**
 * Scans the sources for `Component.register|extend|override` calls and maps every lazily imported
 * component directory to the registration that owns it.
 *
 * This is the codemod's only link between a directory on disk and the name a component is really
 * registered under — the directory basename is a convention, not a guarantee (CMS blocks register
 * `./component`, some pages register `./page/index`). It also separates extend children from plain
 * registrations, which explains most template-inheritance blockers in the report.
 *
 * Inline `Component.override('name', { … })` calls own no directory and never reach the runner's
 * component discovery; they are collected separately so the report can at least mention them.
 */

import * as fs from 'fs';
import * as path from 'path';
import { globSync } from 'glob';

type RegistrationKind = 'register' | 'extend' | 'override';

type Registration = {
    kind: RegistrationKind;
    name: string;
    parent?: string;
};

type InlineOverride = {
    file: string;
    name: string;
};

type ComponentRegistry = {
    byDir: Map<string, Registration>;
    inlineOverrides: InlineOverride[];
    duplicateNames: Set<string>;
};

type CallGroups = {
    kind: RegistrationKind;
    name: string;
    parent?: string;
    specifier?: string;
    inline?: string;
};

const ADMIN_SRC = path.resolve(__dirname, '../../../src');

// One call site, whitespace- and newline-tolerant: an optional `Shopware.` prefix, the component
// name, the extend parent, and finally either an `import()` (with or without the usual factory
// arrow — a single extend call passes the promise directly) or an inline config object.
const REGISTRATION_CALL = new RegExp(
    [
        String.raw`(?:Shopware\s*\.\s*)?Component\s*\.\s*(?<kind>register|extend|override)\s*\(`,
        String.raw`\s*(?<nameQuote>['"])(?<name>[^'"]+)\k<nameQuote>\s*,`,
        String.raw`(?:\s*(?<parentQuote>['"])(?<parent>[^'"]+)\k<parentQuote>\s*,)?`,
        String.raw`\s*(?:(?:\(\s*\)\s*=>\s*)?import\(\s*(?<specQuote>['"])(?<specifier>[^'"]+)\k<specQuote>\s*\)|(?<inline>\{))`,
    ].join(''),
    'g',
);

const INDEX_MODULE = /[\\/]index\.[jt]s$/;

/**
 * A specifier addresses the component through its index module, either by naming the directory
 * (`./sw-foo`) or by spelling the index out (`./page/index`) — both mean the directory holding it.
 * Anything resolving to a differently named module is not a component directory.
 */
function resolveComponentDir(specifier: string, file: string): string | null {
    let resolved: string;

    if (specifier.startsWith('.')) {
        resolved = path.resolve(path.dirname(file), specifier);
    } else if (specifier.startsWith('src/')) {
        resolved = path.resolve(ADMIN_SRC, specifier.slice('src/'.length));
    } else {
        return null;
    }

    const moduleFile = [
        path.join(resolved, 'index.js'),
        path.join(resolved, 'index.ts'),
        `${resolved}.js`,
        `${resolved}.ts`,
    ].find((candidate) => INDEX_MODULE.test(candidate) && fs.existsSync(candidate));

    return moduleFile ? path.dirname(moduleFile) : null;
}

function collectComponentRegistry(scanRoot: string): ComponentRegistry {
    const byDir = new Map<string, Registration>();
    const inlineOverrides: InlineOverride[] = [];
    const dirsByName = new Map<string, Set<string>>();
    const files = globSync('**/*.{js,ts}', { cwd: scanRoot, absolute: true, ignore: '**/node_modules/**' });

    for (const file of files) {
        const source = fs.readFileSync(file, 'utf8');

        for (const match of source.matchAll(REGISTRATION_CALL)) {
            const { kind, name, parent, specifier } = match.groups as CallGroups;

            // No specifier means the call carries an inline config object; only overrides are
            // worth reporting, inline registers and extends are not component directories.
            if (specifier === undefined) {
                if (kind === 'override') {
                    inlineOverrides.push({ file, name });
                }

                continue;
            }

            const dir = resolveComponentDir(specifier, file);

            if (dir === null) {
                continue;
            }

            byDir.set(dir, parent === undefined ? { kind, name } : { kind, name, parent });

            const dirs = dirsByName.get(name) ?? new Set<string>();

            dirs.add(dir);
            dirsByName.set(name, dirs);
        }
    }

    const duplicateNames = new Set<string>();

    for (const [
        name,
        dirs,
    ] of dirsByName) {
        if (dirs.size > 1) {
            duplicateNames.add(name);
        }
    }

    return { byDir, inlineOverrides, duplicateNames };
}

export { collectComponentRegistry, type ComponentRegistry, type Registration, type RegistrationKind, type InlineOverride };
