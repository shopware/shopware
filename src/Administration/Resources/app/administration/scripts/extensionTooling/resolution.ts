/**
 * @sw-package framework
 *
 * Resolvability: a directory of symlinks at `<projectRoot>/node_modules` that
 * makes the installed Administration reachable from every extension in the
 * installation — in the editor, without a single config file.
 *
 * Bare specifiers resolve by walking `node_modules` upwards, and the project root
 * is an ancestor of every extension in both layouts (`custom/plugins/…` in a
 * checkout, `vendor/…` in a Composer install). So one directory at the project
 * root turns `src/*` and every host package into something an extension can
 * resolve, with no `paths` mapping anywhere.
 *
 * The farm is a real directory of symlinks rather than one symlink to the
 * Administration's `node_modules`, because entries of its own live in it — and
 * because nothing may be written into the Administration's dependency tree.
 */

import fs from 'fs';
import path from 'path';
import { toPosix } from './shared';

/** npm never manages this directory; a `.gitignore` of `*` makes it ignore itself. */
export const FARM_DIR = 'node_modules';

/**
 * Auto-included ambient types. TypeScript adds every package under a visible
 * `node_modules/@types` to a program that does not restrict `types` — which is
 * what an editor does for a file with no tsconfig. This is the only route to
 * Administration types with no config at all; `files` needs a config to sit in.
 */
export const AMBIENT_TYPES_PACKAGE = '@types/shopware-admin';

const GITIGNORE_CONTENT = '*\n';

export type FarmOperation =
    | { kind: 'directory'; path: string }
    | { kind: 'file'; path: string; content: string }
    | {
          kind: 'symlink';
          path: string;
          /**
           * Relative to the link's own directory. A Shopware installation is
           * routinely built inside a container and read by an editor on the host,
           * where the same tree is mounted at a different absolute path — an
           * absolute target would dangle on one side of that boundary.
           */
          target: string;
          /** The same target absolute, for Windows junctions, which cannot be relative. */
          absoluteTarget: string;
          directory: boolean;
      };

export interface FarmPlan {
    farmPath: string;
    operations: FarmOperation[];
    /** Entries of the Administration's `node_modules` that could not be inspected. */
    danglingEntries: string[];
}

export interface FarmResult {
    farmPath: string;
    created: number;
    failures: { path: string; message: string }[];
    danglingEntries: string[];
    /** Set when the farm was deliberately not touched; nothing was written. */
    refusal: string | null;
    warnings: string[];
}

function symlinkOperation(linkPath: string, target: string, directory: boolean): FarmOperation {
    return {
        kind: 'symlink',
        path: linkPath,
        target: toPosix(path.relative(path.dirname(linkPath), target)),
        absoluteTarget: target,
        directory,
    };
}

function isDirectory(candidate: string): boolean | null {
    try {
        return fs.statSync(candidate).isDirectory();
    } catch {
        return null;
    }
}

/**
 * Whether an existing `<projectRoot>/node_modules` is one of ours.
 *
 * The `.gitignore` holding exactly `*` is the farm's signature: npm does not
 * write it, and it is what keeps `git status` empty. Anything else at the project
 * root is somebody else's dependency tree and must not be deleted.
 */
export function isManagedFarm(farmPath: string): boolean {
    try {
        return fs.readFileSync(path.join(farmPath, '.gitignore'), 'utf8') === GITIGNORE_CONTENT;
    } catch {
        return false;
    }
}

export function planFarm(projectRoot: string, administrationRoot: string): FarmPlan {
    const farmPath = path.join(projectRoot, FARM_DIR);
    const administrationModules = path.join(administrationRoot, 'node_modules');
    const operations: FarmOperation[] = [{ kind: 'directory', path: farmPath }];
    const danglingEntries: string[] = [];
    const typesOperations: FarmOperation[] = [];

    for (const entry of fs.readdirSync(administrationModules).sort()) {
        const target = path.join(administrationModules, entry);

        // `@types` is mirrored rather than linked, so the ambient package can live
        // beside the host's own type packages without writing into them.
        if (entry === '@types') {
            typesOperations.push({ kind: 'directory', path: path.join(farmPath, '@types') });

            for (const typesEntry of fs.readdirSync(target).sort()) {
                const typesTarget = path.join(target, typesEntry);
                const typesTargetIsDirectory = isDirectory(typesTarget);

                if (typesTargetIsDirectory === null) {
                    danglingEntries.push(path.join('@types', typesEntry));

                    continue;
                }

                typesOperations.push(
                    symlinkOperation(path.join(farmPath, '@types', typesEntry), typesTarget, typesTargetIsDirectory),
                );
            }

            continue;
        }

        const targetIsDirectory = isDirectory(target);

        if (targetIsDirectory === null) {
            danglingEntries.push(entry);

            continue;
        }

        operations.push(symlinkOperation(path.join(farmPath, entry), target, targetIsDirectory));
    }

    operations.push(...typesOperations);

    // The Administration's own sources, as the package its sources import each
    // other as. Added after the dependency links so it wins a name collision.
    operations.push(symlinkOperation(path.join(farmPath, 'src'), path.join(administrationRoot, 'src'), true));

    const ambientDirectory = path.join(farmPath, AMBIENT_TYPES_PACKAGE);

    operations.push(
        { kind: 'directory', path: ambientDirectory },
        {
            kind: 'file',
            path: path.join(ambientDirectory, 'package.json'),
            content: `${JSON.stringify(
                {
                    name: AMBIENT_TYPES_PACKAGE,
                    version: '0.0.0',
                    private: true,
                    types: 'index.d.ts',
                },
                null,
                4,
            )}\n`,
        },
        {
            kind: 'file',
            path: path.join(ambientDirectory, 'index.d.ts'),
            content: `// Generated by "administration:extension:setup". Do not edit.
// Makes the type surface of the installed Administration ambient, so an
// extension needs no tsconfig at all to see it.
/// <reference path="${toPosix(
                path.relative(ambientDirectory, path.join(administrationRoot, 'extension-tooling', 'admin-types.d.ts')),
            )}" />
`,
        },
        // Last, so the signature only exists once the farm is complete.
        { kind: 'file', path: path.join(farmPath, '.gitignore'), content: GITIGNORE_CONTENT },
    );

    return { farmPath, operations, danglingEntries };
}

function createSymlink(operation: Extract<FarmOperation, { kind: 'symlink' }>): void {
    // On Windows a directory symlink needs elevation, a junction does not — but a
    // junction target cannot be relative, so that platform gives up portability.
    if (process.platform === 'win32') {
        fs.symlinkSync(operation.absoluteTarget, operation.path, operation.directory ? 'junction' : 'file');

        return;
    }

    fs.symlinkSync(operation.target, operation.path);
}

/**
 * Replaces the farm, never merges it: an orphaned link from an earlier run is
 * structurally impossible, so nothing has to be remembered across runs. After an
 * `npm ci` in the Administration some links dangle, and one re-run repairs
 * everything.
 */
export function buildFarm(projectRoot: string, administrationRoot: string): FarmResult {
    const farmPath = path.join(projectRoot, FARM_DIR);
    const warnings: string[] = [];
    const empty: FarmResult = { farmPath, created: 0, failures: [], danglingEntries: [], refusal: null, warnings };

    if (!fs.existsSync(path.join(administrationRoot, 'node_modules'))) {
        return {
            ...empty,
            refusal:
                `The Administration's Node dependencies are missing. ` +
                `Run "npm ci" in ${path.relative(projectRoot, administrationRoot)}, then re-run this command.`,
        };
    }

    if (fs.existsSync(farmPath) && !isManagedFarm(farmPath)) {
        return {
            ...empty,
            refusal:
                `${toPosix(farmPath)} exists and was not created by this command. ` +
                'Refusing to replace a dependency tree that belongs to something else — remove it yourself if it is stale.',
        };
    }

    if (fs.existsSync(path.join(projectRoot, 'package.json'))) {
        warnings.push(
            'The project root has a package.json, so npm may manage node_modules there. ' +
                'An "npm install" at the root can remove these links; re-run this command to restore them.',
        );
    }

    const plan = planFarm(projectRoot, administrationRoot);

    fs.rmSync(farmPath, { recursive: true, force: true });

    const failures: FarmResult['failures'] = [];
    let created = 0;

    for (const operation of plan.operations) {
        try {
            if (operation.kind === 'directory') {
                fs.mkdirSync(operation.path, { recursive: true });
            } else if (operation.kind === 'file') {
                fs.writeFileSync(operation.path, operation.content, 'utf8');
            } else {
                createSymlink(operation);
                created += 1;
            }
        } catch (error) {
            failures.push({ path: toPosix(operation.path), message: (error as Error).message });
        }
    }

    return {
        farmPath,
        created,
        failures,
        danglingEntries: plan.danglingEntries,
        refusal: null,
        warnings,
    };
}
