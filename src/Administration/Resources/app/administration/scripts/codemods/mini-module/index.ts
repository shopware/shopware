/**
 * @sw-package framework
 * @private
 *
 * Moves an Options API component into the mini-module layout: the mechanical part only.
 * Usage: npm run codemod:mini-module -- <component directory>
 *
 * It moves the files into `src/` and `tests/`, writes `index.ts` with every member the component has,
 * `register.ts` and `global.d.ts`, replaces the registration, moves the ESLint suppressions,
 * or, for a component its parent imports, moves it as a private module into the `src/` of the parent's module,
 * and takes the code owner from what `.codeowners` already says about the directory.
 * It writes `dependencies.ts` and routes what `src/` takes from outside the module through it, where it can do so
 * without changing behaviour. What needs judgement it prints as a checklist for the sweep after it:
 * what `src/` still takes from outside, contract tests, a missing code owner, and what it could not read.
 */

import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { extractComponentApi, mergeComponentApi, readDeclaredApi, type ComponentApi } from './extract-component-api';
import { findImports, findRegistration, findRegistrationByPath } from './find-registration';
import { isDocumentedPrivate, markExport } from './mark-export';
import { readDeclarationKind, readExtendShape } from './inherit-privacy';
import { dropBaseImport } from './drop-base-import';
import { dropUntestedFiles } from './drop-untested-baseline';
import { findLocalMixins } from './find-local-mixins';
import { dropComponentReexport, exportDefineAsyncComponent, mountNested } from './nest-through-dependencies';
import {
    CODEOWNERS_FILE,
    expectedCodeowners,
    findStatedOwner,
    readManifest,
    writeManifest,
    type Owner,
} from '../../mini-module-owners/owners';
import { isPrivateShape, renderGlobalTypes, renderIndex, renderPrivate, renderRegister, type Shape } from './render';
import { createAdminIndex } from './dependencies/admin-index';
import { routeModule, type RoutedModule } from './dependencies/route-module';
import type { AdminIndex } from './dependencies/model';

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

/**
 * The name of the component in a directory: the directory's name, or, for a directory that is not named after
 * its component such as the `component/` of a CMS element, the name its lazy registration gives it.
 */
const readComponentName = (componentDirectory: string) => {
    const directoryName = path.basename(componentDirectory);
    if (fs.existsSync(path.join(componentDirectory, `${directoryName}.html.twig`)) || locateRegistration(directoryName)) {
        return directoryName;
    }

    for (const file of sourceFiles(`${directoryName}'`)) {
        const absolute = path.join(adminRoot, file);
        const found = findRegistrationByPath(fs.readFileSync(absolute, 'utf8'), (specifier) =>
            leadsToEntry(componentDirectory, path.dirname(absolute), specifier),
        );
        if (found) {
            return found.name;
        }
    }

    return directoryName;
};

/**
 * The declaration of a registered component that is already a mini-module, found by its name:
 * the index.ts of a public module, or the private.ts next to the register.ts of a private one.
 */
const locateMiniModule = (name: string) => {
    for (const file of sourceFiles(`name: '${name}'`)) {
        const absolute = path.join(adminRoot, file);
        const kind = readDeclarationKind(
            file,
            fs.readFileSync(absolute, 'utf8'),
            fs.existsSync(path.join(path.dirname(absolute), 'register.ts')),
        );
        if (kind) {
            return { file, isPrivate: kind === 'private' };
        }
    }

    return null;
};

/** The directory an import path leads to when it names the directory's index file: `./a/index` → `./a`. */
const withoutIndex = (specifier: string) => specifier.replace(/\/index(\.[jt]s)?$/, '');

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
 * What the named component offers, including what it inherits from the components it extends,
 * and the first private component on that chain, or null. A mini-module already offers the lists of its declaration.
 */
const readInheritedApi = (name: string): { api: ComponentApi; privateBase: string | null } => {
    const miniModule = locateMiniModule(name);
    if (miniModule) {
        return {
            api: readDeclaredApi(fs.readFileSync(path.join(adminRoot, miniModule.file), 'utf8')),
            privateBase: miniModule.isPrivate ? name : null,
        };
    }

    const located = locateRegistration(name);
    if (!located) {
        throw new Error(`Found no registration of ${name}, so the codemod cannot read what it offers.`);
    }

    const directory = resolveSpecifier(
        path.dirname(path.join(adminRoot, located.file)),
        withoutIndex(located.registration.specifier),
    );
    const { componentSource, templateSource } = readComponentSource(directory, name);
    const api = extractComponentApi(componentSource, templateSource);
    api.unresolved = api.unresolved.map((item) => `${item} (inherited from ${name})`);
    const isPrivate = located.registration.isPrivate || isDocumentedPrivate(componentSource);

    if (located.registration.kind !== 'extend') {
        return { api, privateBase: isPrivate ? name : null };
    }

    const inherited = readInheritedApi(located.registration.base);
    return {
        api: mergeComponentApi(api, inherited.api),
        privateBase: isPrivate ? name : inherited.privateBase,
    };
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
 * An import through the `src/…` alias stays one, and changes only when its target moved.
 * It covers `from`, bare side-effect imports, `import()`, `require()` and `jest.mock()`.
 *
 * @example
 * // src/sw-x.js was index.js: '../../service/a.service' → '../../../service/a.service'
 * // tests/sw-x.spec.js: 'src/app/component/sw-x/sw-x.helper' → 'src/app/component/sw-x/src/sw-x.helper'
 */
const rewriteMovedImports = (moves: Map<string, string>, from: string, to: string) => {
    const file = path.join(adminRoot, to);
    const source = fs.readFileSync(file, 'utf8');
    const rewritten = source.replace(
        /((?:from|import\(|import|require\(|jest\.mock\()\s*')((?:\.\.?\/|src\/)[^']+)'/g,
        (statement, prefix: string, specifier: string) => {
            const isAlias = specifier.startsWith('src/');
            const target = isAlias ? specifier : relative(path.resolve(path.dirname(path.join(adminRoot, from)), specifier));
            const moved = movedPath(moves, target);
            const written = SCRIPT_EXTENSION.test(specifier) ? moved : moved.replace(SCRIPT_EXTENSION, '');

            if (isAlias) {
                return moved === target ? statement : `${prefix}${written}'`;
            }

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

/**
 * The mini-module whose `src/` or dependencies.ts holds a file, or null: the directory with an `index.ts` or a `private.ts`,
 * a `src/` and a `tests/`, as `eslint-rules/core-rules/mini-module.js` defines it.
 */
const findEnclosingModule = (file: string) => {
    for (let directory = path.dirname(file); directory.startsWith('src/'); directory = path.dirname(directory)) {
        const absolute = path.join(adminRoot, directory);
        const isMiniModule =
            ['index.ts', 'private.ts'].some((entry) => fs.existsSync(path.join(absolute, entry))) &&
            ['src', 'tests'].every((folder) => fs.existsSync(path.join(absolute, folder)));

        if (isMiniModule) {
            return file.startsWith(`${directory}/src/`) || file === `${directory}/dependencies.ts` ? directory : null;
        }
    }

    return null;
};

/** The imports of the component's entry in the files outside its directory, except specs, which move or mock it. */
const findImporters = (componentDirectory: string, name: string) =>
    sourceFiles(name)
        .filter((file) => !path.join(adminRoot, file).startsWith(`${componentDirectory}${path.sep}`))
        .filter((file) => !/\.spec\.[jt]s$/.test(file))
        .map((file) => {
            const absolute = path.join(adminRoot, file);
            const imports = findImports(fs.readFileSync(absolute, 'utf8'), (specifier) =>
                leadsToEntry(componentDirectory, path.dirname(absolute), specifier),
            );

            return { file, imports };
        })
        .filter(({ imports }) => imports.length > 0);

/**
 * Points the files that import a nested component at the private.ts in its new place, and returns them.
 * They are in the `src/` of the module around it, which may import it without a disabled lint line.
 */
const importThroughPrivate = (importers: ReturnType<typeof findImporters>, root: string) =>
    importers.map(({ file, imports }) => {
        const absolute = path.join(adminRoot, file);
        const privatePath = importPath(absolute, path.join(adminRoot, root, 'private'));
        const rewritten = [...imports]
            .reverse()
            .reduce(
                (code, { start, end }) => `${code.slice(0, start)}${privatePath}${code.slice(end)}`,
                fs.readFileSync(absolute, 'utf8'),
            );

        fs.writeFileSync(absolute, rewritten);
        return file;
    });

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

/** The script files of a module's own `src/`, without specs and without the nested modules in it. */
const moduleSourceFiles = (moduleRoot: string): string[] => {
    const walk = (directory: string): string[] =>
        fs.readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
            const file = path.join(directory, entry.name);
            if (entry.isDirectory()) {
                return fs.existsSync(path.join(file, 'private.ts')) || fs.existsSync(path.join(file, 'index.ts'))
                    ? []
                    : walk(file);
            }

            return /\.[jt]s$/.test(entry.name) && !/\.(spec|d)\.[jt]s$/.test(entry.name) ? [file] : [];
        });

    return walk(path.join(adminRoot, moduleRoot, 'src'));
};

/**
 * The new content of each file of the parent module that reaches a nested component through its dependencies.ts:
 * dependencies.ts no longer re-exports it, and `src/` mounts it from its private.ts with `defineAsyncComponent`.
 * Returns a string that says why when that is not safe, so the codemod moves nothing.
 */
const planNestingThroughDependencies = (
    importers: ReturnType<typeof findImporters>,
    componentDirectory: string,
    parent: string,
    name: string,
): Map<string, string> | string => {
    const planned = new Map<string, string>();
    const dependencies = importers.filter(({ file }) => file === `${parent}/dependencies.ts`);
    for (const { file } of dependencies) {
        const absolute = path.join(adminRoot, file);
        const dropped = dropComponentReexport(fs.readFileSync(absolute, 'utf8'), (specifier) =>
            leadsToEntry(componentDirectory, path.dirname(absolute), specifier),
        );
        if (!dropped) {
            return `${file} re-exports more of ${name} than its component, so the codemod cannot nest it: move it by hand.`;
        }

        planned.set(absolute, exportDefineAsyncComponent(dropped.code));
        for (const source of moduleSourceFiles(parent)) {
            const privatePath = importPath(source, path.join(adminRoot, parent, 'src', name, 'private'));
            const code = dropped.names.reduce<string | null>(
                (current, local) => (current === null ? null : mountNested(current, local, privatePath)),
                planned.get(source) ?? fs.readFileSync(source, 'utf8'),
            );
            if (code === null) {
                return `${relative(source)} uses ${name} outside its components, so the codemod cannot mount it lazily: move it by hand.`;
            }

            planned.set(source, code);
        }
    }

    return planned;
};

/** The script files below a directory, as absolute paths. */
const scriptFilesBelow = (directory: string) =>
    fs
        .readdirSync(directory, { recursive: true, encoding: 'utf8' })
        .filter((file) => SCRIPT_EXTENSION.test(file))
        .map((file) => path.join(directory, file));

/** Whether an import path, written in a file of the given directory, leads to the file. */
const leadsToFile = (fromDirectory: string, specifier: string, file: string) => {
    if (!specifier.startsWith('.') && !specifier.startsWith('src/')) {
        return false;
    }

    const target = resolveSpecifier(fromDirectory, specifier);
    const withoutExtension = file.replace(SCRIPT_EXTENSION, '');
    return [file, withoutExtension].includes(target) || `${target}/index` === withoutExtension;
};

/** The script files of src/ and test/ that contain the text, also specs. */
const filesMentioning = (text: string) => {
    try {
        return git('grep', '-lF', text, '--', 'src/**/*.js', 'src/**/*.ts', 'test/**/*.js', 'test/**/*.ts')
            .split('\n')
            .filter(Boolean);
    } catch (error) {
        if ((error as { status?: number }).status === 1) {
            return [];
        }

        throw error;
    }
};

/**
 * Why the component cannot move yet, or null: a file in its directory besides the component, such as a store,
 * that code outside the directory imports would break, and a mixin only the registry reaches cannot be imported.
 */
const findBlocker = (componentDirectory: string, entry: string, index: AdminIndex): string | null => {
    const files = scriptFilesBelow(componentDirectory).filter(
        (file) => file !== path.join(componentDirectory, entry) && !/\.spec\.[jt]s$|\.spec\//.test(file),
    );

    for (const file of files) {
        const importers = filesMentioning(path.basename(file).replace(SCRIPT_EXTENSION, ''))
            .filter((importer) => !path.join(adminRoot, importer).startsWith(`${componentDirectory}${path.sep}`))
            .filter((importer) => {
                const absolute = path.join(adminRoot, importer);
                return (
                    findImports(fs.readFileSync(absolute, 'utf8'), (specifier) =>
                        leadsToFile(path.dirname(absolute), specifier, file),
                    ).length > 0
                );
            });

        if (importers.length > 0) {
            return `${relative(componentDirectory)} is not moved: the shared file ${relative(file)} is imported by ${importers.join(', ')}.
Extract it into its own module first.`;
        }
    }

    const localMixins = [
        ...new Set(
            scriptFilesBelow(componentDirectory)
                .filter((file) => !/\.spec\.[jt]s$|\.spec\//.test(file))
                .flatMap((file) => findLocalMixins(fs.readFileSync(file, 'utf8'), index.isModuleLocalMixin)),
        ),
    ];

    return localMixins.length > 0
        ? `${relative(componentDirectory)} is not moved: it uses the module-local mixin ${localMixins.join(', ')}.
It moves after the mixin gets its own module.`
        : null;
};

type Reading = {
    shape: Shape;
    registration: ReturnType<typeof locateRegistration>;
    importers: ReturnType<typeof findImporters>;
    inheritedApi?: ComponentApi;
    nesting?: Map<string, string>;
};

/**
 * How the component reaches the page today: the statement that registers or extends it, or, without one,
 * the parent that imports it. A string says why the component cannot move yet.
 * A component is private when its registration or the doc comment of its own default export says `@private`,
 * and an extending one also when it inherits from a private component, see `readExtendShape()`.
 */
const readShape = (componentDirectory: string, name: string, componentSource: string): Reading | string => {
    const registration = locateRegistration(name);
    if (registration?.registration.kind === 'extend') {
        const { base, isPrivate: isRegistrationPrivate } = registration.registration;
        const inherited = readInheritedApi(base);

        return {
            shape: readExtendShape({ base, isRegistrationPrivate, componentSource, privateBase: inherited.privateBase }),
            registration,
            importers: [],
            inheritedApi: inherited.api,
        };
    }

    if (registration) {
        const isPrivate = registration.registration.isPrivate || isDocumentedPrivate(componentSource);
        return { shape: { kind: 'register', isPrivate }, registration, importers: [] };
    }

    const importers = findImporters(componentDirectory, name);
    if (importers.length === 0) {
        throw new Error(`Found no registration of ${name} and no file that imports it.`);
    }

    const parents = [...new Set(importers.map(({ file }) => findEnclosingModule(file)))];
    if (parents.length !== 1 || parents[0] === null) {
        const files = importers.map(({ file }) => path.dirname(file)).join(', ');
        return `${name} has no global name, so it becomes a private module inside the mini-module that imports it.
It moves with ${files} when the parent is migrated: run the codemod on the parent first, then on ${name} again.`;
    }

    const nesting = planNestingThroughDependencies(importers, componentDirectory, parents[0], name);
    if (typeof nesting === 'string') {
        return nesting;
    }

    return { shape: { kind: 'nested', parent: parents[0] }, registration, importers, nesting };
};

function run(directory: string) {
    const componentDirectory = path.resolve(adminRoot, directory);
    const name = readComponentName(componentDirectory);
    const origin = relative(componentDirectory);

    if (git('status', '--porcelain', '--', origin).trim()) {
        throw new Error(`${origin} has uncommitted changes. Commit them first, so git can undo this run.`);
    }

    const { entry, componentSource, templateSource } = readComponentSource(componentDirectory, name);
    const packageName = /@sw-package (\S+)/.exec(componentSource)?.[1] ?? 'framework';
    const reading = readShape(componentDirectory, name, componentSource);
    if (typeof reading === 'string') {
        console.log(reading);
        return;
    }

    const { shape, registration, importers, inheritedApi, nesting } = reading;
    const root = shape.kind === 'nested' ? `${shape.parent}/src/${name}` : origin;
    const moduleDirectory = path.join(adminRoot, root);
    const adminIndex = createAdminIndex(adminRoot, moduleDirectory);
    const blocker = findBlocker(componentDirectory, entry, adminIndex);
    if (blocker) {
        console.log(blocker);
        return;
    }

    const ownApi = extractComponentApi(componentSource, templateSource);
    const api = inheritedApi ? mergeComponentApi(ownApi, inheritedApi) : ownApi;
    const target = { name, packageName, shape };

    const moves = new Map<string, string>();
    const move = (from: string, to: string) => {
        fs.mkdirSync(path.dirname(path.join(adminRoot, to)), { recursive: true });
        git('mv', from, to);
        moves.set(from, to);
    };

    for (const file of fs.readdirSync(componentDirectory)) {
        const from = `${origin}/${file}`;
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
        } else if (fs.statSync(path.join(adminRoot, to)).isDirectory()) {
            // A spec split into a directory, such as `sw-x.spec/`, moved with every file in it.
            fs.readdirSync(path.join(adminRoot, to), { recursive: true, encoding: 'utf8' })
                .filter((file) => SCRIPT_EXTENSION.test(file))
                .forEach((file) => rewriteMovedImports(moves, `${from}/${file}`, `${to}/${file}`));
        }
    }

    // Specs elsewhere, such as a parent's, may import the moved files: point them at the new places.
    const outsideSpecs = sourceFiles(path.basename(origin)).filter(
        (file) => /\.spec\.[jt]s$/.test(file) && !file.startsWith(`${origin}/`) && !file.startsWith(`${root}/`),
    );
    outsideSpecs.forEach((file) => rewriteMovedImports(moves, file, file));

    const baseline = path.join(adminRoot, 'src/meta/baseline.ts');
    fs.writeFileSync(baseline, dropUntestedFiles(fs.readFileSync(baseline, 'utf8'), [...moves.keys()]));

    const sourceFile = path.join(adminRoot, root, 'src', `${name}${path.extname(entry)}`);
    const source = markExport(fs.readFileSync(sourceFile, 'utf8'), isPrivateShape(shape) ? '@private' : '@public');
    fs.writeFileSync(sourceFile, shape.kind === 'extend' ? dropBaseImport(source, shape.base) : source);
    keepTestsDirectory(moduleDirectory);

    const entryFiles = {
        'index.ts': renderIndex(target, api),
        'register.ts': renderRegister(target),
        'global.d.ts': renderGlobalTypes(target),
        'private.ts': renderPrivate(target, api),
    };
    const written = Object.entries(entryFiles).flatMap(([file, content]) => {
        if (content === null) {
            return [];
        }

        fs.writeFileSync(path.join(moduleDirectory, file), content);
        return [`${root}/${file}`];
    });

    nesting?.forEach((code, file) => fs.writeFileSync(file, code));
    const sourceImporters = importers.filter(({ file }) => !file.endsWith('/dependencies.ts'));
    const changed = registration
        ? [replaceRegistration(registration)]
        : [...importThroughPrivate(sourceImporters, root), ...[...(nesting?.keys() ?? [])].map(relative)];

    if (entryFiles['global.d.ts'] !== null && !isPrivateShape(shape)) {
        const miniModuleTypes = path.join(adminRoot, 'extension-tooling', 'mini-modules.d.ts');
        fs.writeFileSync(
            miniModuleTypes,
            `${fs.readFileSync(miniModuleTypes, 'utf8').replace(/^export \{\};\n/m, '')}import '../${root}/global';\n`,
        );
    }

    moveSuppressions(moves);
    const owner = isPrivateShape(shape) ? null : addOwner(root);
    const routed = routeModule({
        adminRoot,
        moduleRoot: moduleDirectory,
        name,
        packageName,
        index: adminIndex,
    });
    execFileSync(
        path.join(adminRoot, 'node_modules', '.bin', 'prettier'),
        [
            '--write',
            ...written,
            ...changed,
            ...routed.changed,
            ...routed.outsideFiles,
        ],
        { cwd: adminRoot },
    );
    printSweepChecklist(root, name, api, shape, owner, routed);
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
    fs.writeFileSync(absolute, addImport(withoutRegistration, `${withoutIndex(registration.specifier)}/register`));

    return file;
};

/** What the codemod changed outside the module so `src/` can import names instead of writing them, for the checklist. */
const describeOutsideEdits = ({ outside }: RoutedModule) => {
    const lines = [
        ...new Set(
            outside.map((edit) => {
                if (edit.kind === 'service-name') {
                    return `   - src/app/service-names exports the new constant ${edit.name}: say in its doc comment what the service does.`;
                }

                const kind = edit.kind === 'store-id' ? 'store id' : 'filter name';
                return `   - ${relative(edit.file)} exports the ${kind} ${edit.exportName}.`;
            }),
        ),
    ];

    return lines.length > 0
        ? `   It changed these files outside the module, which the PR lists:\n${lines.join('\n')}\n`
        : '';
};

/** Why an extending component is private, or what its public lists publish, when it inherits from a private one. */
const describePrivateBase = (name: string, shape: Extract<Shape, { kind: 'extend' }>) => {
    if (!shape.privateBase) {
        return '';
    }

    return shape.isPrivate
        ? `${name} inherits from the private ${shape.privateBase}, so it is private too: an override of it reaches what it inherits.\n`
        : `${name} is marked @public but inherits from the private ${shape.privateBase}, so its lists publish what it inherits.
   Ask the owning team whether that is intended; if not, undo the run, mark ${name} @private and run the codemod again.\n`;
};

/** What the sweep must know about how the component reaches the page, for the checklist. */
const describeShape = (name: string, shape: Shape) => {
    const privateNote =
        shape.kind !== 'nested' && shape.isPrivate
            ? `${name} is private, so it is a private module: private.ts declares it with definePrivateComponent(),
and global.d.ts adds it to PrivateComponents, which types Component.override() inside the Administration only.
It is not published to extensions and has no code owner entry.
`
            : '';

    if (shape.kind === 'extend') {
        return `
${privateNote}${describePrivateBase(name, shape)}${name} extends ${shape.base}: register.ts calls Shopware.Component.extend(), and the lists include what it inherits.
TypeScript cannot check inherited names; the meta test src/meta/public-components.spec.js checks them against the base.
`;
    }

    if (shape.kind === 'register' && shape.isPrivate) {
        return `\n${privateNote}`;
    }

    if (shape.kind === 'nested') {
        return `
${name} is no global component, so it is now a private module in the src/ of ${shape.parent}, the module that mounts it.
private.ts declares it with definePrivateComponent(), and its lists hold only what the parent uses.
It has no index.ts, register.ts or global.d.ts, and needs no code owner: changes to it go through the parent's module.
Where the parent reached it through its dependencies.ts, the codemod mounts it already:
   SwExample: defineAsyncComponent(SwExample.load), with defineAsyncComponent from the parent's dependencies.ts.
Where the parent imported it directly, it now imports the declaration: mount it the same way until the page works again.
   A template ref of the parent is typed with PrivateInstance<typeof SwExample>.
`;
    }

    return '';
};

/** What the sweep must do about the code owner, for the checklist. */
const describeOwner = (root: string, shape: Shape, owner: Owner | null) =>
    isPrivateShape(shape)
        ? 'A private module has no code owner entry: nothing outside the Administration relies on it.'
        : owner === null
          ? `.codeowners states no owner for ${root}, so the module is not in mini-module-owners.json.
   Ask which team owns it, add it there (a "? " in front keeps the team optional), and run composer admin:generate-mini-module-owners.`
          : `mini-module-owners.json names ${owner} for the module, as .codeowners did for its directory, and .codeowners has its lines.
   Keep the level: an optional owner ("? ") stays optional.`;

function printSweepChecklist(
    root: string,
    name: string,
    api: ComponentApi,
    shape: Shape,
    owner: Owner | null,
    routed: RoutedModule,
) {
    const entryFile = isPrivateShape(shape) ? 'private.ts' : 'index.ts';
    const apiSpec = isPrivateShape(shape)
        ? 'If present, members only other Administration code reaches go into private-api.spec.ts, which may read them through wrapper.vm; each test names its member with @private-api.'
        : 'If present, public members that no user path reaches go into public-api.spec.ts, which may read them through wrapper.vm; each test names its member with @public-api.';
    const changesServiceNames = routed.outside.some((edit) => edit.kind === 'service-name');

    console.log(`# Sweep ${root}

The codemod moved ${name} into the mini-module layout. Finish it by hand, following src/module/AGENTS.md "Mini-Modules".
A finished example is src/module/sw-settings-shipping/component/sw-settings-shipping-price-matrix: copy its patterns.
This is a refactor: behaviour must not change, so keep odd code, unused injects and the mixin order.
${describeShape(name, shape)}
1. The codemod wrote dependencies.ts and routed src/ through it where that cannot change behaviour.
${describeOutsideEdits(routed)}   Left for the sweep, because the codemod cannot rewrite them safely:
${routed.leftovers.map((line) => `   - ${line}`).join('\n') || '   - nothing'}
   - A name in inject that is no service, such as one a parent provides, stays written: name it in the PR.
   - A lookup by a computed name, Shopware passed around, or a write to the global needs judgement.
   - Keep dependencies.ts free of side effects: anything that reads state that changes is a function.
2. Complete the lists in ${entryFile} where the codemod could not read them:
${api.unresolved.map((item) => `   - ${item}`).join('\n') || '   - nothing unresolved'}
   Add one sentence to the doc comment of ${entryFile} that says what the component is for.
3. Add tests/<name>.harness.ts and the contract tests: requirements.spec.ts tells the module's story from the user's view, details go in pinned.spec.ts.
   The harness may arrange state through ../dependencies. The moved specs in tests/ stay workshop specs.
   ${apiSpec}
   If present, bugs that must not come back go into regressions.spec.ts.
   A contract spec over 500 lines splits into a directory, e.g. tests/pinned.spec/search.spec.ts.
   Delete tests/.gitkeep, if present, once tests/ has a spec.
4. ${describeOwner(root, shape, owner)}
5. ${
        shape.kind === 'nested'
            ? 'Jest knows no name for a nested component, so the harness mounts (await SwExample.load()).default from ../private.'
            : shape.isPrivate
              ? 'Run npm run unit-setup, so Jest finds the moved component by its name.'
              : `Run npm run generate-shopware-modules, so extensions can import the module as shopware:components/${name}.
   Run npm run unit-setup, so Jest finds the moved component by its name.`
    }${
        changesServiceNames && isPrivateShape(shape)
            ? '\n   Run npm run generate-shopware-modules, because src/app/service-names exports new names.'
            : ''
    }
   Then run eslint with --prune-suppressions on the module, tsc, and the module's tests: npm run unit:mini-module -- ${root} --contracts
   Lines the contract tests leave uncovered are code no contract protects: pin them, or name them in the PR.
`);
}

run(process.argv[2] ?? '');
