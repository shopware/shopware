/**
 * @sw-package framework
 * @private
 */

import { execFile, spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { promisify } from 'node:util';
import { mentionsName } from './names';
import type { Repository } from './select';

const run = promisify(execFile);

const STOREFRONT_ADMINISTRATION = '../../../../Storefront/Resources/app/administration';
const COMPONENT_IMPORT_MAP = 'test/_helper_/componentWrapper/component-imports.js';

/** Jest calls in parallel: each one reads the whole haste map, so more mostly costs memory. */
const JEST_CONCURRENCY = 4;

type ComponentEntry = { name: string; directory: string; extends?: string };

/**
 * The repository as the selection sees it: the file system, the component import map, and Jest's own import graph.
 * It asks Jest (`--listTests --findRelatedTests`) instead of resolving imports itself, so aliases, the
 * `moduleNameMapper` and the custom resolver work exactly as in a test run. Runs where Jest runs: in the container.
 *
 * @example
 * const repository = new FileSystemRepository('/var/www/html/src/Administration/Resources/app/administration');
 * await repository.importersOf('src/app/service/file-validation.service.ts');
 * // → { specs: ['src/app/service/file-validation.service.spec.js', ...], setupFiles: [] }
 */
export class FileSystemRepository implements Repository {
    private readonly root: string;

    private jestPatterns: string[] | null = null;

    private componentEntries: ComponentEntry[] | null = null;

    private specSources: Map<string, string> | null = null;

    private runningJestCalls = 0;

    private readonly waitingJestCalls: Array<() => void> = [];

    constructor(root: string) {
        this.root = root;
    }

    miniModuleOf(file: string): string | null {
        for (
            let directory = path.posix.dirname(file);
            /\/src\//.test(`/${directory}`);
            directory = path.posix.dirname(directory)
        ) {
            // A nested private module has only a private.ts.
            const isMiniModule =
                ['index.ts', 'private.ts'].some((entry) => fs.existsSync(this.absolute(`${directory}/${entry}`))) &&
                this.isDirectory(`${directory}/src`);

            if (isMiniModule) {
                return directory;
            }
        }

        return null;
    }

    specsIn(directory: string): string[] {
        return this.filesIn(directory).filter((file) => /\.spec\.[jt]s$/.test(file));
    }

    read(file: string): string | null {
        const absolute = this.absolute(file);

        return fs.existsSync(absolute) && fs.statSync(absolute).isFile() ? fs.readFileSync(absolute, 'utf8') : null;
    }

    async importersOf(file: string): Promise<{ specs: string[]; setupFiles: string[] }> {
        await this.acquireJestSlot();

        try {
            const { stdout } = await run(
                path.join(this.root, 'node_modules/.bin/jest'),
                [
                    '--config',
                    'jest.config.js',
                    '--listTests',
                    '--findRelatedTests',
                    this.absolute(file),
                    '--testMatch',
                    ...this.patterns(),
                ],
                { cwd: this.root, maxBuffer: 64 * 1024 * 1024 },
            );
            const related = stdout
                .split('\n')
                .filter((line) => line.startsWith('/'))
                .map((line) => this.relative(line));

            return {
                specs: related.filter((related) => /\.spec[./]/.test(related)),
                setupFiles: related.filter((related) => !/\.spec[./]/.test(related)),
            };
        } finally {
            this.releaseJestSlot();
        }
    }

    componentsOwning(file: string): string[] {
        const withoutExtension = file.replace(/\.[^/.]+$/, '');
        const owners = this.components().filter(
            ({ directory }) => withoutExtension === directory || file.startsWith(`${directory}/`),
        );
        const deepest = Math.max(...owners.map(({ directory }) => directory.length));

        return owners.filter(({ directory }) => directory.length === deepest).map(({ name }) => name);
    }

    componentsExtending(name: string): string[] {
        return this.components()
            .filter((entry) => entry.extends === name)
            .map((entry) => entry.name);
    }

    specsMentioning(name: string): string[] {
        if (!this.specSources) {
            this.specSources = new Map(
                ['src', `${STOREFRONT_ADMINISTRATION}/src`]
                    .flatMap((directory) => this.specsIn(directory))
                    .map((spec) => [spec, this.read(spec) ?? '']),
            );
        }

        return [...this.specSources].filter(([, source]) => mentionsName(source, name)).map(([spec]) => spec);
    }

    /**
     * The `testMatch` patterns for asking Jest about importers: the config's own spec patterns plus the Jest
     * setup files and what they load by computed path, which the import graph cannot see from the setup files.
     */
    private patterns(): string[] {
        if (this.jestPatterns) {
            return this.jestPatterns;
        }

        const config = loadJestConfig(this.root);
        const setupFiles = [...(config.setupFiles ?? []), ...(config.setupFilesAfterEnv ?? [])].filter((file) =>
            fs.existsSync(file),
        );
        const loadedByPath = setupFiles.flatMap((setupFile) =>
            [...fs.readFileSync(setupFile, 'utf8').matchAll(/['"](src\/[\w./-]+)['"]/g)].map(([, loaded]) =>
                this.globFor(loaded),
            ),
        );

        this.jestPatterns = [
            ...(config.testMatch ?? []).map((pattern) => pattern.replace('<rootDir>', this.root)),
            ...setupFiles,
            ...loadedByPath,
        ];

        return this.jestPatterns;
    }

    /** A setup file loads `src/app/mixin/index`, which loads every mixin by `import.meta.glob`: so the whole directory counts. */
    private globFor(loaded: string): string {
        const directory = loaded.replace(/\/index$/, '');

        if (this.isDirectory(directory)) {
            return `${this.absolute(directory)}/**/*.{js,ts}`;
        }

        return fs.existsSync(this.absolute(loaded)) ? this.absolute(loaded) : `${this.absolute(loaded)}.{js,ts}`;
    }

    private components(): ComponentEntry[] {
        if (this.componentEntries) {
            return this.componentEntries;
        }

        const source = this.read(COMPONENT_IMPORT_MAP);
        if (source === null) {
            throw new Error(`${COMPONENT_IMPORT_MAP} is missing. Run \`npm run unit-setup\` first.`);
        }

        const map = JSON.parse(source.slice(source.indexOf('{'), source.lastIndexOf('}') + 1)) as Record<
            string,
            { p: string; en?: string }
        >;
        this.componentEntries = Object.entries(map).map(([name, entry]) => ({
            name,
            directory: entry.p.replace(/\/index(\.[jt]s)?$/, '').replace(/\.[jt]s$/, ''),
            extends: entry.en,
        }));

        return this.componentEntries;
    }

    private filesIn(directory: string): string[] {
        if (!this.isDirectory(directory)) {
            return [];
        }

        return fs
            .readdirSync(this.absolute(directory), { recursive: true, withFileTypes: true })
            .filter((entry) => entry.isFile() && !entry.parentPath.includes('node_modules'))
            .map((entry) => this.relative(path.join(entry.parentPath, entry.name)))
            .sort();
    }

    private isDirectory(file: string): boolean {
        const absolute = this.absolute(file);

        return fs.existsSync(absolute) && fs.statSync(absolute).isDirectory();
    }

    private absolute(file: string): string {
        return path.resolve(this.root, file);
    }

    private relative(file: string): string {
        return path.relative(this.root, file).split(path.sep).join('/');
    }

    private async acquireJestSlot(): Promise<void> {
        if (this.runningJestCalls >= JEST_CONCURRENCY) {
            await new Promise<void>((resolve) => {
                this.waitingJestCalls.push(resolve);
            });
        }
        this.runningJestCalls += 1;
    }

    private releaseJestSlot(): void {
        this.runningJestCalls -= 1;
        this.waitingJestCalls.shift()?.();
    }
}

type JestConfig = { testMatch?: string[]; setupFiles?: string[]; setupFilesAfterEnv?: string[] };

/** Loads the Administration's Jest config without the "Run Jest in local mode" line it logs. */
function loadJestConfig(root: string): JestConfig {
    const { info } = console;
    console.info = () => {};

    try {
        return require(path.join(root, 'jest.config.js')) as JestConfig;
    } finally {
        console.info = info;
    }
}

/**
 * The files changed since `base` (default: the merge base with `origin/trunk`), committed or not, including
 * untracked ones, relative to the Administration root. Covers the Storefront's admin code too, which Jest also runs.
 * Throws when git does not work, as in the container of a git worktree.
 *
 * @example
 * changedFilesFromGit('/…/administration', 'HEAD~1'); // → ['src/app/main.ts', 'src/app/main.spec.js']
 */
export function changedFilesFromGit(root: string, base: string | null): string[] {
    const git = (cwd: string, args: string[]) => {
        const result = spawnSync('git', args, { cwd, encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 });
        if (result.status !== 0) {
            throw new Error(`git ${args.join(' ')} failed: ${result.stderr || result.error?.message}`);
        }

        return result.stdout.split('\n').filter(Boolean);
    };

    const ref = base ?? git(root, ['merge-base', 'origin/trunk', 'HEAD'])[0];

    return [
        { directory: root, prefix: '' },
        { directory: path.resolve(root, STOREFRONT_ADMINISTRATION), prefix: `${STOREFRONT_ADMINISTRATION}/` },
    ].flatMap(({ directory, prefix }) =>
        [
            ...git(directory, [
                'diff',
                '--name-only',
                '--relative',
                ref,
            ]),
            ...git(directory, ['ls-files', '--others', '--exclude-standard']),
        ].map((file) => `${prefix}${file}`),
    );
}
