/**
 * @sw-package framework
 * @private
 *
 * Moves an Options API component into the mini-module layout: the mechanical part only.
 * Usage: npm run codemod:mini-module -- <component directory>
 *
 * It moves the files into `src/` and `tests/`, writes `index.ts` with every member the component has,
 * `register.ts` and `global.d.ts`, replaces the registration, moves the ESLint suppressions,
 * and takes the code owner from what `.codeowners` already says about the directory.
 * What needs judgement it prints as a checklist for the sweep after it:
 * `dependencies.ts`, contract tests, a missing code owner, and what it could not read.
 */

import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { extractComponentApi, mergeComponentApi, readDeclaredApi, type ComponentApi } from './extract-component-api';
import { findImports, findRegistration } from './find-registration';
import { markPublic } from './mark-public';
import { dropBaseImport } from './drop-base-import';
import {
    CODEOWNERS_FILE,
    expectedCodeowners,
    findStatedOwner,
    readManifest,
    writeManifest,
    type Owner,
} from '../../mini-module-owners/owners';
import { renderGlobalTypes, renderIndex, renderPrivate, renderRegister, type Shape } from './render';

const adminRoot = path.resolve(__dirname, '../../..');
const relative = (file: string) => path.relative(adminRoot, file).split(path.sep).join('/');
const git = (...args: string[]) => execFileSync('git', args, { cwd: adminRoot, encoding: 'utf8' });

/** Where an import path leads, from the directory of the file that holds it; `src/…` is the Administration alias. */
const resolveSpecifier = (fromDirectory: string, specifier: string) =>
    specifier.startsWith('src/') ? path.join(adminRoot, specifier) : path.resolve(fromDirectory, specifier);

/** The path from one file to another, as an import writes it. */
const importPath = (fromFile: string, to: string) => {
    const relativePath = path.relative(path.dirname(fromFile), to).split(path.sep).join('/');
    return relativePath.startsWith('.') ? relativePath : `./${relativePath}`;
};

/** The script files below src/ that contain the text, or none: `git grep` exits with 1 when nothing matches. */
const sourceFiles = (text: string) => {
    try {
        return git('grep', '-lF', text, '--', 'src/**/*.js', 'src/**/*.ts').split('\n').filter(Boolean);
    } catch (error) {
        if ((error as { status?: number }).status === 1) {
            return [];
        }

        throw error;
    }
};

/** Finds the statement that registers or extends the component by its name, and the file that holds it. */
const locateRegistration = (name: string) => {
    for (const file of sourceFiles(`'${name}'`)) {
        const registration = findRegistration(fs.readFileSync(path.join(adminRoot, file), 'utf8'), name);
        if (registration) {
            return { file, registration };
        }
    }

    return null;
};

/** The directory of a component that is already a mini-module, found by the name its index.ts declares. */
const locateMiniModule = (name: string) =>
    sourceFiles(`name: '${name}'`)
        .filter((file) => file.endsWith('/index.ts'))
        .find((file) => fs.readFileSync(path.join(adminRoot, file), 'utf8').includes('definePublicComponent('));

const readComponentSource = (componentDirectory: string, name: string) => {
    const entry = ['index.js', 'index.ts'].find((file) => fs.existsSync(path.join(componentDirectory, file)));
    if (!entry) {
        throw new Error(`${relative(componentDirectory)} has no index.js or index.ts.`);
    }

    const templateFile = path.join(componentDirectory, `${name}.html.twig`);
    return {
        entry,
        componentSource: fs.readFileSync(path.join(componentDirectory, entry), 'utf8'),
        templateSource: fs.existsSync(templateFile) ? fs.readFileSync(templateFile, 'utf8') : '',
    };
};

/**
 * Reads what the named component offers, including what it inherits from the components it extends.
 * A component that is a mini-module already offers the lists of its index.ts.
 */
const readInheritedApi = (name: string): ComponentApi => {
    const miniModule = locateMiniModule(name);
    if (miniModule) {
        return readDeclaredApi(fs.readFileSync(path.join(adminRoot, miniModule), 'utf8'));
    }

    const located = locateRegistration(name);
    if (!located) {
        throw new Error(`Found no registration of ${name}, so the codemod cannot read what it offers.`);
    }

    const directory = resolveSpecifier(
        path.dirname(path.join(adminRoot, located.file)),
        located.registration.specifier.replace(/\/index(\.[jt]s)?$/, ''),
    );
    const { componentSource, templateSource } = readComponentSource(directory, name);
    const api = extractComponentApi(componentSource, templateSource);
    api.unresolved = api.unresolved.map((item) => `${item} (inherited from ${name})`);

    return located.registration.kind === 'extend'
        ? mergeComponentApi(api, readInheritedApi(located.registration.base))
        : api;
};

const addImport = (source: string, specifier: string) => {
    const imports = [...source.matchAll(/^import .+;$/gm)];
    const at = imports.length > 0 ? imports[imports.length - 1].index + imports[imports.length - 1][0].length + 1 : 0;

    return `${source.slice(0, at)}import '${specifier}';\n${source.slice(at)}`;
};

const SCRIPT_EXTENSION = /\.[jt]s$/;

/**
 * Where a path relative to the Administration root ends up after the moves, or itself when it did not move.
 * It tries the extensions and `index` files an import may leave out.
 */
const movedPath = (moves: Map<string, string>, target: string) => {
    const candidates = [
        target,
        ...[
            '.js',
            '.ts',
            '/index.js',
            '/index.ts',
        ].map((suffix) => `${target}${suffix}`),
    ];

    for (const candidate of candidates) {
        for (const [from, to] of moves) {
            if (candidate === from || candidate.startsWith(`${from}/`)) {
                return `${to}${candidate.slice(from.length)}`;
            }
        }
    }

    return target;
};

/**
 * Rewrites the relative imports of a moved file, so they still reach their target from the file's new place,
 * also when the target moved: a spec in tests/ that imported './index' now imports '../src/<name>'.
 *
 * @example
 * // src/sw-x.js was index.js: '../../service/a.service' → '../../../service/a.service'
 */
const rewriteMovedImports = (moves: Map<string, string>, from: string, to: string) => {
    const file = path.join(adminRoot, to);
    const source = fs.readFileSync(file, 'utf8');
    const rewritten = source.replace(
        /((?:from|import\(|require\(|jest\.mock\()\s*')(\.\.?\/[^']+)'/g,
        (statement, prefix: string, specifier: string) => {
            const target = relative(path.resolve(path.dirname(path.join(adminRoot, from)), specifier));
            const moved = movedPath(moves, target);
            const written = SCRIPT_EXTENSION.test(specifier) ? moved : moved.replace(SCRIPT_EXTENSION, '');

            return `${prefix}${importPath(file, path.join(adminRoot, written))}'`;
        },
    );

    if (rewritten !== source) {
        fs.writeFileSync(file, rewritten);
    }
};

/** Whether an import path leads to the component's entry file, from the directory of the importing file. */
const leadsToEntry = (componentDirectory: string, fromDirectory: string, specifier: string) => {
    const target = resolveSpecifier(fromDirectory, specifier).replace(/\/index(\.[jt]s)?$/, '');
    return target === componentDirectory;
};

const BOUNDARY_REASON = 'has no global name, so its parent mounts the loaded component';

/**
 * Points the files that import a local component at its private.ts, with the line that allows that.
 * Returns the files it changed.
 */
const importThroughPrivate = (componentDirectory: string, name: string) =>
    sourceFiles(name)
        .filter((file) => !path.join(adminRoot, file).startsWith(`${componentDirectory}${path.sep}`))
        .filter((file) => {
            const absolute = path.join(adminRoot, file);
            const source = fs.readFileSync(absolute, 'utf8');
            const imports = findImports(source, (specifier) =>
                leadsToEntry(componentDirectory, path.dirname(absolute), specifier),
            );

            const rewritten = [...imports].reverse().reduce((code, { specifier, start, end, lineStart }) => {
                const privatePath = `${specifier.replace(/\/index(\.[jt]s)?$/, '')}/private`;
                const indent = /^[ \t]*/.exec(code.slice(lineStart))?.[0] ?? '';
                const allowance = `${indent}// eslint-disable-next-line sw-core-rules/mini-module-boundaries -- ${name} ${BOUNDARY_REASON}\n`;

                return `${code.slice(0, lineStart)}${allowance}${code.slice(lineStart, start)}${privatePath}${code.slice(end)}`;
            }, source);

            if (imports.length > 0) {
                fs.writeFileSync(absolute, rewritten);
            }

            return imports.length > 0;
        });

/**
 * The entry file of the Administration module around a component, which loads its other components,
 * so a local component's register.ts is imported where the registrations of its siblings are.
 */
const findModuleEntry = (componentDirectory: string) => {
    for (let directory = path.dirname(componentDirectory); directory.startsWith(path.join(adminRoot, 'src')); ) {
        const entry = ['index.js', 'index.ts']
            .map((file) => path.join(directory, file))
            .find((file) => fs.existsSync(file) && /\b(Component|Module)\.register\(/.test(fs.readFileSync(file, 'utf8')));
        if (entry) {
            return relative(entry);
        }

        directory = path.dirname(directory);
    }

    throw new Error(`Found no module entry above ${relative(componentDirectory)} to import its register.ts.`);
};

const moveSuppressions = (moves: Map<string, string>) => {
    const file = path.join(adminRoot, 'eslint-suppressions.json');
    const suppressions = JSON.parse(fs.readFileSync(file, 'utf8')) as Record<string, unknown>;
    const moved = Object.fromEntries(Object.entries(suppressions).map(([key, value]) => [moves.get(key) ?? key, value]));
    const sorted = Object.fromEntries(
        Object.keys(moved)
            .sort()
            .map((key) => [key, moved[key]]),
    );

    fs.writeFileSync(file, JSON.stringify(sorted, null, 2));
};

/**
 * How the component reaches the page today: the statement that registers or extends it, or, without one,
 * the parent that imports it.
 */
const readShape = (name: string): { shape: Shape; registration: ReturnType<typeof locateRegistration> } => {
    const registration = locateRegistration(name);
    if (!registration) {
        return { shape: { kind: 'local' }, registration };
    }

    const shape: Shape =
        registration.registration.kind === 'extend'
            ? { kind: 'extend', base: registration.registration.base }
            : { kind: 'register' };

    return { shape, registration };
};

function run(directory: string) {
    const componentDirectory = path.resolve(adminRoot, directory);
    const name = path.basename(componentDirectory);
    const root = relative(componentDirectory);

    if (git('status', '--porcelain', '--', root).trim()) {
        throw new Error(`${root} has uncommitted changes. Commit them first, so git can undo this run.`);
    }

    const { entry, componentSource, templateSource } = readComponentSource(componentDirectory, name);
    const packageName = /@sw-package (\S+)/.exec(componentSource)?.[1] ?? 'framework';
    const { shape, registration } = readShape(name);
    const ownApi = extractComponentApi(componentSource, templateSource);
    const api = shape.kind === 'extend' ? mergeComponentApi(ownApi, readInheritedApi(shape.base)) : ownApi;
    const target = { name, packageName, shape };

    const moves = new Map<string, string>();
    const move = (from: string, to: string) => {
        fs.mkdirSync(path.dirname(path.join(adminRoot, to)), { recursive: true });
        git('mv', from, to);
        moves.set(from, to);
    };

    for (const file of fs.readdirSync(componentDirectory)) {
        const from = `${root}/${file}`;
        if (file === entry) {
            move(from, `${root}/src/${name}${path.extname(entry)}`);
        } else if (/\.spec(\.[jt]s)?$/.test(file)) {
            move(from, `${root}/tests/${file}`);
        } else {
            move(from, `${root}/src/${file}`);
        }
    }

    for (const [from, to] of moves) {
        if (SCRIPT_EXTENSION.test(to)) {
            rewriteMovedImports(moves, from, to);
        }
    }

    const sourceFile = path.join(adminRoot, root, 'src', `${name}${path.extname(entry)}`);
    const source = markPublic(fs.readFileSync(sourceFile, 'utf8'));
    fs.writeFileSync(sourceFile, shape.kind === 'extend' ? dropBaseImport(source, shape.base) : source);
    keepTestsDirectory(componentDirectory);

    const entryFiles = {
        'index.ts': renderIndex(target, api),
        'register.ts': renderRegister(target),
        'global.d.ts': renderGlobalTypes(target),
        'private.ts': renderPrivate(target),
    };
    const written = Object.entries(entryFiles).flatMap(([file, content]) => {
        if (content === null) {
            return [];
        }

        fs.writeFileSync(path.join(componentDirectory, file), content);
        return [`${root}/${file}`];
    });

    const changed = registration
        ? [replaceRegistration(registration)]
        : [...importThroughPrivate(componentDirectory, name), importRegisterInModule(componentDirectory)];

    if (entryFiles['global.d.ts'] !== null) {
        const miniModuleTypes = path.join(adminRoot, 'extension-tooling', 'mini-modules.d.ts');
        fs.writeFileSync(
            miniModuleTypes,
            `${fs.readFileSync(miniModuleTypes, 'utf8').replace(/^export \{\};\n/m, '')}import '../${root}/global';\n`,
        );
    }

    moveSuppressions(moves);
    const owner = addOwner(root);
    execFileSync(path.join(adminRoot, 'node_modules', '.bin', 'prettier'), ['--write', ...written, ...changed], {
        cwd: adminRoot,
    });
    printSweepChecklist(root, name, api, shape, owner);
}

/**
 * Makes sure `tests/` exists in git also when the component had no spec, because the generator and the lint
 * recognise a mini-module by its `src/` and `tests/`. Spec files are written when there is something to test.
 */
const keepTestsDirectory = (componentDirectory: string) => {
    const tests = path.join(componentDirectory, 'tests');
    fs.mkdirSync(tests, { recursive: true });

    if (fs.readdirSync(tests).length === 0) {
        fs.writeFileSync(path.join(tests, '.gitkeep'), '');
    }
};

/**
 * Adds the module to mini-module-owners.json with the owner `.codeowners` already states for its directory,
 * at the same level, and rewrites the generated block. Returns the owner, or null when none is stated.
 */
const addOwner = (root: string): Owner | null => {
    const owner = findStatedOwner(fs.readFileSync(CODEOWNERS_FILE, 'utf8'), root);
    if (owner === null) {
        return null;
    }

    writeManifest({ ...readManifest(), [root]: owner });
    fs.writeFileSync(CODEOWNERS_FILE, expectedCodeowners());

    return owner;
};

/** Replaces the registration statement with an import of the module's register.ts, and returns the file. */
const replaceRegistration = ({ file, registration }: NonNullable<ReturnType<typeof locateRegistration>>) => {
    const absolute = path.join(adminRoot, file);
    const source = fs.readFileSync(absolute, 'utf8');
    const withoutRegistration = `${source.slice(0, registration.start)}${source.slice(registration.end)}`;
    fs.writeFileSync(absolute, addImport(withoutRegistration, `${registration.specifier}/register`));

    return file;
};

/** Imports a local component's register.ts in the module entry, so the module is published, and returns the file. */
const importRegisterInModule = (componentDirectory: string) => {
    const file = findModuleEntry(componentDirectory);
    const absolute = path.join(adminRoot, file);
    fs.writeFileSync(
        absolute,
        addImport(fs.readFileSync(absolute, 'utf8'), importPath(absolute, path.join(componentDirectory, 'register'))),
    );

    return file;
};

const findLines = (directory: string, pattern: RegExp) =>
    fs
        .readdirSync(directory, { recursive: true, encoding: 'utf8' })
        .filter((file) => /\.[jt]s$/.test(file))
        .flatMap((file) =>
            fs
                .readFileSync(path.join(directory, file), 'utf8')
                .split('\n')
                .flatMap((line, index) => (pattern.test(line) ? [`src/${file}:${index + 1}: ${line.trim()}`] : [])),
        );

/** What the sweep must know about how the component reaches the page, for the checklist. */
const describeShape = (name: string, shape: Shape) => {
    if (shape.kind === 'extend') {
        return `
${name} extends ${shape.base}: register.ts calls Shopware.Component.extend(), and the public lists include what it inherits.
TypeScript cannot check inherited names; the meta test src/meta/public-components.spec.js checks them against the base.
`;
    }

    if (shape.kind === 'local') {
        return `
${name} is no global component: its parent mounts it locally and now imports it through private.ts.
register.ts only publishes the module, so there is no global.d.ts, and the public lists hold only what a parent uses.
`;
    }

    return '';
};

/** What the sweep must do about the code owner, for the checklist. */
const describeOwner = (root: string, owner: Owner | null) =>
    owner === null
        ? `.codeowners states no owner for ${root}, so the module is not in mini-module-owners.json.
   Ask which team owns it, add it there (a "? " in front keeps the team optional), and run composer admin:generate-mini-module-owners.`
        : `mini-module-owners.json names ${owner} for the module, as .codeowners did for its directory, and .codeowners has its lines.
   Keep the level: an optional owner ("? ") stays optional.`;

function printSweepChecklist(root: string, name: string, api: ComponentApi, shape: Shape, owner: Owner | null) {
    const outside = findLines(
        path.join(adminRoot, root, 'src'),
        /Shopware\.|= Shopware;|inject:|getByName\(|(from|import) 'src\//,
    );

    console.log(`# Sweep ${root}

The codemod moved ${name} into the mini-module layout. Finish it by hand, following src/module/AGENTS.md "Mini-Modules".
A finished example is src/module/sw-settings-shipping/component/sw-settings-shipping-price-matrix: copy its patterns.
This is a refactor: behaviour must not change, so keep odd code, unused injects and the mixin order.
${describeShape(name, shape)}
1. Write dependencies.ts and route every outside access of src/ through it:
${outside.map((line) => `   - ${line}`).join('\n') || '   - nothing found'}
   - A service name in inject or in Shopware.Service('x') becomes a constant from src/app/service-names; add a missing one there.
     Shopware.Service(x) becomes a function in dependencies.ts: export const getXService = () => Shopware.Service(xService);
   - A store id in Shopware.Store.get() becomes the id the store file exports, wrapped in a function such as useXStore().
     If the store file exports no id yet, export it there, like sw-settings-shipping-detail/store.ts does.
   - A filter in Shopware.Filter.getByName('x') becomes the name the filter file exports; export one if it has none.
   - A new constant in src/app/service-names is public API owned by framework: add only what the module injects.
   - Mixin.getByName('x') becomes the default import of the mixin's file.
   - Shopware.Component.wrapComponentConfig is Vue's defineComponent, so a TypeScript component takes it from there:
     /** @private */
     export { defineComponent as wrapComponentConfig } from 'vue';
2. Complete the public lists in index.ts where the codemod could not read them:
${api.unresolved.map((item) => `   - ${item}`).join('\n') || '   - nothing unresolved'}
   Add one sentence to the doc comment of index.ts that says what the component is for.
3. Add tests/<name>.harness.ts and the contract tests: requirements.spec.ts tells the module's story from the user's view, details go in pinned.spec.ts.
   The harness may arrange state through ../dependencies. The moved specs in tests/ stay workshop specs.
   If present, public members that no user path reaches go into public-api.spec.ts, which may read them through wrapper.vm; each test names its member with @public-api.
   If present, bugs that must not come back go into regressions.spec.ts.
   A contract spec over 500 lines splits into a directory, e.g. tests/pinned.spec/search.spec.ts.
   Delete tests/.gitkeep, if present, once tests/ has a spec.
4. ${describeOwner(root, owner)}
5. Run npm run generate-shopware-modules, so extensions can import the module as shopware:components/${name}.
   Run npm run unit-setup, so Jest finds the moved component by its name.
   Then run eslint with --prune-suppressions on the module, tsc, and the module's tests: npm run unit:mini-module -- ${root} --contracts
   Lines the contract tests leave uncovered are code no contract protects: pin them, or name them in the PR.
`);
}

run(process.argv[2] ?? '');
