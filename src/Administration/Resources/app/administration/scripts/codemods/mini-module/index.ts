/**
 * @sw-package framework
 * @private
 *
 * Moves an Options API component into the mini-module layout: the mechanical part only.
 * Usage: npm run codemod:mini-module -- <component directory>
 *
 * It moves the files into `src/` and `tests/`, writes `index.ts` with every member the component has,
 * `register.ts` and `global.d.ts`, replaces the registration, and moves the ESLint suppressions.
 * What needs judgement it prints as a checklist for the sweep after it:
 * `dependencies.ts`, contract tests, the code owners, and what it could not read.
 */

import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { extractComponentApi, type ComponentApi } from './extract-component-api';

const adminRoot = path.resolve(__dirname, '../../..');
const relative = (file: string) => path.relative(adminRoot, file).split(path.sep).join('/');
const git = (...args: string[]) => execFileSync('git', args, { cwd: adminRoot, encoding: 'utf8' });

const formatList = (names: string[]) =>
    names.length === 0 ? '[]' : `[\n${names.map((name) => `            '${name}',`).join('\n')}\n        ]`;

const renderIndex = (name: string, packageName: string, api: ComponentApi) => {
    const lists = (
        [
            'props',
            'emits',
            'slots',
            'blocks',
            'computed',
            'methods',
        ] as const
    )
        .filter((list) => api[list].length > 0)
        .map((list) => `        ${list}: ${formatList(api[list])},`)
        .join('\n');

    return `import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package ${packageName}
 *
 * This file is the public API, also for extensions. The files in \`./src\` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: '${name}',
    load: () => import('./src/${name}'),
    public: {
${lists}
    },
});
`;
};

const renderRegister = (packageName: string) => `import Component from './index';

/**
 * @sw-package ${packageName}
 */

Shopware.Component.register(Component);
`;

const renderGlobalTypes = (name: string, packageName: string) => `import type Component from './index';

/**
 * @sw-package ${packageName}
 */

declare global {
    interface PublicComponents {
        '${name}': typeof Component;
    }
}
`;

/** Finds the `Shopware.Component.register('<name>', () => import('...'))` call and the file that holds it. */
const findRegistration = (name: string) => {
    const pattern = new RegExp(
        `^[ \\t]*Shopware\\.Component\\.register\\(\\s*'${name}',\\s*\\(\\) => import\\('([^']+)'\\),?\\s*\\);\\n`,
        'm',
    );
    const candidates = git('grep', '-l', `'${name}'`, '--', 'src/**/*.js', 'src/**/*.ts').split('\n').filter(Boolean);

    for (const file of candidates) {
        const source = fs.readFileSync(path.join(adminRoot, file), 'utf8');
        const match = pattern.exec(source);
        if (match) {
            return { file, statement: match[0], specifier: match[1] };
        }
    }

    throw new Error(`No plain Shopware.Component.register('${name}', () => import()) found.`);
};

const addImport = (source: string, specifier: string) => {
    const imports = [...source.matchAll(/^import .+;$/gm)];
    const at = imports.length > 0 ? imports[imports.length - 1].index + imports[imports.length - 1][0].length + 1 : 0;

    return `${source.slice(0, at)}import '${specifier}';\n${source.slice(at)}`;
};

const markPublic = (source: string) =>
    source
        .replace(
            /^\/\/ eslint-disable-next-line sw-deprecation-rules\/private-feature-declarations\n(?=export default)/m,
            '',
        )
        .replace(/^(\/\*\*(?:(?!\*\/)[\s\S])*?)\n \*\/\n(export default)/m, '$1\n *\n * @public\n */\n$2');

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

function run(directory: string) {
    const componentDirectory = path.resolve(adminRoot, directory);
    const name = path.basename(componentDirectory);
    const root = relative(componentDirectory);

    if (git('status', '--porcelain', '--', root).trim()) {
        throw new Error(`${root} has uncommitted changes. Commit them first, so git can undo this run.`);
    }

    const entry = ['index.js', 'index.ts'].find((file) => fs.existsSync(path.join(componentDirectory, file)));
    if (!entry) {
        throw new Error(`${root} has no index.js or index.ts.`);
    }

    const componentSource = fs.readFileSync(path.join(componentDirectory, entry), 'utf8');
    const templateFile = path.join(componentDirectory, `${name}.html.twig`);
    const templateSource = fs.existsSync(templateFile) ? fs.readFileSync(templateFile, 'utf8') : '';
    const api = extractComponentApi(componentSource, templateSource);
    const packageName = /@sw-package (\S+)/.exec(componentSource)?.[1] ?? 'framework';
    const registration = findRegistration(name);

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

    const sourceFile = path.join(adminRoot, root, 'src', `${name}${path.extname(entry)}`);
    fs.writeFileSync(sourceFile, markPublic(fs.readFileSync(sourceFile, 'utf8')));
    fs.mkdirSync(path.join(componentDirectory, 'tests'), { recursive: true });
    fs.writeFileSync(path.join(componentDirectory, 'index.ts'), renderIndex(name, packageName, api));
    fs.writeFileSync(path.join(componentDirectory, 'register.ts'), renderRegister(packageName));
    fs.writeFileSync(path.join(componentDirectory, 'global.d.ts'), renderGlobalTypes(name, packageName));

    const registrationFile = path.join(adminRoot, registration.file);
    const registrationSource = fs.readFileSync(registrationFile, 'utf8').replace(registration.statement, '');
    fs.writeFileSync(registrationFile, addImport(registrationSource, `${registration.specifier}/register`));

    const miniModuleTypes = path.join(adminRoot, 'extension-tooling', 'mini-modules.d.ts');
    fs.writeFileSync(
        miniModuleTypes,
        `${fs.readFileSync(miniModuleTypes, 'utf8').replace(/^export \{\};\n/m, '')}import '../${root}/global';\n`,
    );

    moveSuppressions(moves);
    execFileSync(
        path.join(adminRoot, 'node_modules', '.bin', 'prettier'),
        ['--write', ...['index.ts', 'register.ts', 'global.d.ts'].map((file) => `${root}/${file}`), registration.file],
        { cwd: adminRoot },
    );
    printSweepChecklist(root, name, packageName, api);
}

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

function printSweepChecklist(root: string, name: string, packageName: string, api: ComponentApi) {
    const outside = findLines(path.join(adminRoot, root, 'src'), /Shopware\.|= Shopware;|inject:|getByName\(|from 'src\//);

    console.log(`# Sweep ${root}

The codemod moved ${name} into the mini-module layout. Finish it by hand, following src/module/AGENTS.md "Mini-Modules":

1. Write dependencies.ts and route every outside access of src/ through it (string names stay strings, imported from their owner; side effects become functions):
${outside.map((line) => `   - ${line}`).join('\n') || '   - nothing found'}
2. Complete the public lists in index.ts by hand where the codemod could not read them:
${api.unresolved.map((item) => `   - ${item}`).join('\n') || '   - nothing unresolved'}
3. Add a test harness and contract tests: rough requirements.spec.ts from the user's view, details in pinned.spec.ts.
   The moved specs in tests/ stay workshop specs.
4. Add .codeowners entries for index.ts, global.d.ts and tests/requirements.spec.ts (@sw-package ${packageName}).
5. Run eslint, tsc and the module's tests: npm run unit:mini-module -- ${root}
`);
}

run(process.argv[2] ?? '');
