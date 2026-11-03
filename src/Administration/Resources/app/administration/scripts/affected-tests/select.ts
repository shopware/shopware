/**
 * @sw-package framework
 * @private
 */

import { dirname } from 'node:path';
import { findPublicComponentName, findRegistrations, type RegisteredName } from './names';

/**
 * What the selection needs to know about the repository. Paths are relative to the Administration root.
 * The real one asks the file system and Jest; a spec passes a fake graph.
 */
export interface Repository {
    /** The innermost mini-module directory (with `index.ts` or `private.ts`, and `src/`) that contains the file, or null. */
    miniModuleOf(file: string): string | null;
    /** The spec files inside a directory. */
    specsIn(directory: string): string[];
    /** The content of a file, or null when it does not exist, for example because the diff deletes it. */
    read(file: string): string | null;
    /**
     * Everything that imports the file directly or through other files, by the static import graph:
     * the specs, and the Jest setup files, which every spec loads.
     */
    importersOf(file: string): Promise<{ specs: string[]; setupFiles: string[] }>;
    /** The components whose directory holds the file, by the component import map that `wrapTestComponent()` uses. */
    componentsOwning(file: string): string[];
    /** The components that `Component.extend()` from a component. */
    componentsExtending(name: string): string[];
    /** The specs that mention a name as a whole word. */
    specsMentioning(name: string): string[];
}

/** A changed file the selection cannot vouch for, and why. */
export type Uncertainty = { file: string; reason: string };

/**
 * The specs a diff can break, each with the reasons it was picked,
 * and the changed files for which no static analysis can promise that the rest of the suite still passes.
 */
export type Selection = {
    specs: Map<string, Set<string>>;
    uncertain: Uncertainty[];
    skipped: Uncertainty[];
};

const SPEC = /\.spec[./]/;

/** Files every spec runs on: a change there can break any spec. */
const TEST_INFRASTRUCTURE = [
    /^test\//,
    /^_?jest\.config\.[jt]s$/,
    /^package(-lock)?\.json$/,
    /^tsconfig\.json$/,
    /^scripts\/componentImportResolver\//,
    /^build\/vite-plugins\/virtual-shopware-modules\//,
];

/** Files no spec can observe, like styles, which Jest replaces by a mock. */
const INVISIBLE_TO_JEST = [
    { pattern: /\.(s?css|less)$/, reason: 'styles are mocked in Jest' },
    { pattern: /\.md$/, reason: 'documentation' },
];

/** Files the app loads but specs do not, unless a spec imports them: then the import graph finds that spec. */
const UNUSED_BY_SPECS = [
    { pattern: /\/snippet\/[^/]+\.json$/, reason: 'specs show snippet keys and do not load snippet files' },
    { pattern: /^(eslint\.config\.ts|eslint-suppressions\.json)$/, reason: 'lint configuration' },
];

/**
 * Picks the specs that a set of changed files can break.
 *
 * Inside a mini-module, the edges are explicit: a change in `tests/` needs only the module's own specs, because other
 * code imports the module only through `index.ts` and relies on its contract tests.
 * A change in `src/` or `dependencies.ts` adds the specs that mount the component by name, because they render its
 * implementation; in a module without a component it adds the importers of `index.ts` and `private.ts` instead.
 * Changes to `index.ts`, `private.ts` or `global.d.ts` add their importers, and `register.ts` adds the specs that
 * mention the component name.
 * Outside mini-modules, it takes the import graph plus every spec that mentions a name the file registers or
 * belongs to, and reports the files for which that is not enough.
 *
 * @example
 * await selectAffectedSpecs(['src/core/order/primary-delivery/src/primary-delivery.ts'], repository);
 * // → { specs: Map { 'src/core/order/primary-delivery/tests/requirements.spec.ts' => Set { 'mini-module ... changed in src/' } }, ... }
 */
export async function selectAffectedSpecs(changedFiles: string[], repository: Repository): Promise<Selection> {
    const selection: Selection = { specs: new Map(), uncertain: [], skipped: [] };
    const select = (spec: string, reason: string) => {
        selection.specs.set(spec, (selection.specs.get(spec) ?? new Set()).add(reason));
    };

    await Promise.all(
        changedFiles.map(async (file) => {
            if (SPEC.test(file) && repository.read(file) !== null) {
                select(file, 'the spec itself changed');
            }

            const invisible = INVISIBLE_TO_JEST.find(({ pattern }) => pattern.test(file));
            if (invisible) {
                selection.skipped.push({ file, reason: invisible.reason });
                return;
            }

            if (TEST_INFRASTRUCTURE.some((pattern) => pattern.test(file))) {
                if (!SPEC.test(file)) {
                    selection.uncertain.push({ file, reason: 'changes the test setup that every spec runs on' });
                }
                return;
            }

            const miniModule = repository.miniModuleOf(file);
            if (miniModule) {
                await selectForMiniModule(file, miniModule, repository, select, selection.uncertain);
            } else if (!SPEC.test(file)) {
                await selectByGraphAndNames(file, repository, select, selection);
            }
        }),
    );

    return selection;
}

type Select = (spec: string, reason: string) => void;

async function selectForMiniModule(
    file: string,
    miniModule: string,
    repository: Repository,
    select: Select,
    uncertain: Uncertainty[],
): Promise<void> {
    const part = file.slice(miniModule.length + 1);
    const area = part.includes('/') ? `${part.split('/')[0]}/` : part;

    repository
        .specsIn(`${miniModule}/tests`)
        .forEach((spec) => select(spec, `mini-module ${miniModule} changed in ${area}`));

    const publicEntries = {
        'index.ts': ['index.ts'],
        'private.ts': ['private.ts'],
        'global.d.ts': ['index.ts', 'private.ts', 'global.d.ts'],
        'register.ts': ['register.ts'],
    }[part];

    for (const entry of publicEntries ?? []) {
        const entryFile = `${miniModule}/${entry}`;
        if (repository.read(entryFile) !== null) {
            await selectImporters(entryFile, repository, select, uncertain, file);
        }
    }

    const mountsByName = area === 'src/' || area === 'dependencies.ts';
    if (part !== 'register.ts' && !mountsByName) {
        return;
    }

    const name = findPublicComponentName(
        repository.read(`${miniModule}/index.ts`) ?? repository.read(`${miniModule}/private.ts`) ?? '',
    );
    if (!name) {
        if (part === 'register.ts' && /\bComponent\.(register|extend)\(/.test(repository.read(file) ?? '')) {
            uncertain.push({ file, reason: 'registers a component whose name index.ts does not declare' });
        }
        if (mountsByName) {
            await selectImplementationUsers(miniModule, repository, select, uncertain, file);
        }
        return;
    }

    if (part === 'register.ts') {
        selectMentions([{ kind: 'component', name }], repository, select);
        return;
    }

    // Specs elsewhere that mount the real component by name see its implementation, not only its contract.
    const ownSpecs = new Set(repository.specsIn(`${miniModule}/tests`));
    selectMentions(
        [{ kind: 'component', name }],
        repository,
        (spec, reason) => {
            if (!ownSpecs.has(spec)) {
                select(spec, reason);
            }
        },
        (mentioned, via) => `mounts component ${mentioned} by name${via}`,
    );
}

/**
 * Selects the specs that import a module without a component, which run its implementation the way specs that mount
 * a component by name run the component's.
 */
async function selectImplementationUsers(
    miniModule: string,
    repository: Repository,
    select: Select,
    uncertain: Uncertainty[],
    changedFile: string,
): Promise<void> {
    for (const entry of ['index.ts', 'private.ts']) {
        const entryFile = `${miniModule}/${entry}`;
        if (repository.read(entryFile) !== null) {
            await selectImporters(entryFile, repository, select, uncertain, changedFile);
        }
    }
}

async function selectByGraphAndNames(
    file: string,
    repository: Repository,
    select: Select,
    { uncertain, skipped }: Selection,
): Promise<void> {
    const source = repository.read(file);
    const found = new Set<string>();
    const selectAndCount: Select = (spec, reason) => {
        found.add(spec);
        select(spec, reason);
    };

    if (source === null) {
        uncertain.push({ file, reason: 'is deleted, so the import graph no longer shows who used it' });
    } else {
        await selectImporters(file, repository, selectAndCount, uncertain, file);
    }

    const registrations = findRegistrations(source ?? '');
    registrations.dynamic.forEach((call) => {
        uncertain.push({ file, reason: `registers a name that only exists at runtime: ${call}` });
    });

    const names: RegisteredName[] = [
        ...registrations.names,
        ...repository.componentsOwning(file).map((name) => ({ kind: 'component' as const, name })),
        ...overridesNextTo(file, repository),
    ];
    selectMentions(names, repository, selectAndCount);

    if (source === null || found.size > 0 || names.length > 0 || registrations.dynamic.length > 0) {
        return;
    }

    const unused = UNUSED_BY_SPECS.find(({ pattern }) => pattern.test(file));
    if (unused) {
        skipped.push({ file, reason: unused.reason });
    } else {
        uncertain.push({
            file,
            reason: 'no spec imports it and it registers no name: it may be loaded by import.meta.glob or by a name the runner cannot see',
        });
    }
}

/** Component overrides in the `index` file of the same directory, which a template or a helper next to it belongs to. */
function overridesNextTo(file: string, repository: Repository): RegisteredName[] {
    if (/(^|\/)index\.[jt]s$/.test(file)) {
        return [];
    }

    const directory = dirname(file);
    const index = repository.read(`${directory}/index.ts`) ?? repository.read(`${directory}/index.js`) ?? '';

    return findRegistrations(index).names.filter(({ kind }) => kind === 'component');
}

async function selectImporters(
    entry: string,
    repository: Repository,
    select: Select,
    uncertain: Uncertainty[],
    changedFile: string,
): Promise<void> {
    const { specs, setupFiles } = await repository.importersOf(entry);
    specs.forEach((spec) => select(spec, `imports ${entry}`));

    if (setupFiles.length > 0) {
        uncertain.push({
            file: changedFile,
            reason: `${entry} is loaded by the Jest setup (${setupFiles.join(', ')}), so every spec can reach it through the Shopware global`,
        });
    }
}

/**
 * Selects the specs that mention the names, and for components also the names of the components extending them.
 * `describe` words the reason, for example "mounts component sw-foo by name".
 */
function selectMentions(
    names: RegisteredName[],
    repository: Repository,
    select: Select,
    describe: (name: string, via: string, kind: string) => string = (name, via, kind) =>
        `mentions ${kind} name ${name}${via}`,
): void {
    const seen = new Set<string>();
    const queue = [...names.map((name) => ({ ...name, via: '' }))];

    while (queue.length > 0) {
        const { kind, name, via } = queue.shift()!;
        if (seen.has(`${kind}:${name}`)) {
            continue;
        }
        seen.add(`${kind}:${name}`);

        repository.specsMentioning(name).forEach((spec) => select(spec, describe(name, via, kind)));

        if (kind === 'component') {
            repository.componentsExtending(name).forEach((child) => {
                queue.push({ kind, name: child, via: ` (extends ${name})` });
            });
        }
    }
}

/**
 * Turns a path relative to the repository root, as git prints it, into a path relative to the Administration root.
 * Other paths stay as they are, because the command line takes paths relative to the Administration root too.
 *
 * @example
 * toAdministrationPath('src/Administration/Resources/app/administration/src/app/main.ts'); // → 'src/app/main.ts'
 * toAdministrationPath('src/Storefront/Resources/app/administration/src/main.js');
 * // → '../../../../Storefront/Resources/app/administration/src/main.js'
 * toAdministrationPath('src/app/main.ts'); // → 'src/app/main.ts'
 */
export function toAdministrationPath(path: string): string {
    const relative = path.replace(/^\.\//, '');
    const administration = 'src/Administration/Resources/app/administration/';
    const storefront = 'src/Storefront/Resources/app/administration/';

    if (relative.startsWith(administration)) {
        return relative.slice(administration.length);
    }
    if (relative.startsWith(storefront)) {
        return `../../../../Storefront/Resources/app/administration/${relative.slice(storefront.length)}`;
    }

    return relative;
}
