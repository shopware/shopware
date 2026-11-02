/**
 * @sw-package framework
 * @private
 *
 * Runs the Jest specs a diff can break: npm run unit:affected -- [<changed file>...] [--base <ref>] [--dry-run]
 * Without files it asks git. In the container of a git worktree, where git does not work, take the files from the host:
 * npm run -s unit:affected -- --changed-files
 */

import { spawnSync } from 'node:child_process';
import path from 'node:path';
import { parseArguments } from './arguments';
import { changedFilesFromGit, FileSystemRepository } from './repository';
import { selectAffectedSpecs, toAdministrationPath, type Selection } from './select';

const adminRoot = path.resolve(__dirname, '../..');

async function main(): Promise<number> {
    const options = parseArguments(process.argv.slice(2));
    const changedFiles = [
        ...new Set(options.files.length > 0 ? options.files.map(toAdministrationPath) : changedFilesOrExplain(options.base)),
    ];

    if (options.changedFiles) {
        console.log(changedFiles.join('\n'));
        return 0;
    }

    console.log(`${changedFiles.length} changed file(s), selecting specs...`);
    const selection = await selectAffectedSpecs(changedFiles, new FileSystemRepository(adminRoot));
    const specs = [...selection.specs.keys()].sort();

    printSelection(selection, options.dryRun);

    if (options.dryRun || specs.length === 0) {
        return 0;
    }

    const { status } = spawnSync(
        path.join(adminRoot, 'node_modules/.bin/jest'),
        [
            '--config',
            'jest.config.js',
            '--coverage=false',
            '--runTestsByPath',
            ...specs,
        ],
        { cwd: adminRoot, stdio: 'inherit', shell: false },
    );
    printUncertainty(selection);

    return status ?? 1;
}

function changedFilesOrExplain(base: string | null): string[] {
    try {
        return changedFilesFromGit(adminRoot, base);
    } catch (error) {
        throw new Error(
            `${(error as Error).message}\n` +
                'git does not work here (the container of a git worktree?). Pass the changed files instead, from the host:\n' +
                '  npm run unit:affected -- $(npm run -s unit:affected -- --changed-files)',
        );
    }
}

function printSelection(selection: Selection, withReasons: boolean): void {
    const specs = [...selection.specs].sort(([a], [b]) => a.localeCompare(b));

    console.log(`\n${specs.length} spec(s) selected:`);
    specs.forEach(([spec, reasons]) => {
        console.log(`  ${spec}`);
        if (withReasons) {
            [...reasons].sort().forEach((reason) => console.log(`      ${reason}`));
        }
    });

    selection.skipped.forEach(({ file, reason }) => console.log(`\nSkipped ${file}: ${reason}`));
    printUncertainty(selection);
}

function printUncertainty(selection: Selection): void {
    if (selection.uncertain.length === 0) {
        return;
    }

    console.warn('\nWARNING: the import graph and the name search cannot rule out other specs for these files:');
    selection.uncertain
        .sort((a, b) => a.file.localeCompare(b.file))
        .forEach(({ file, reason }) => console.warn(`  ${file}\n      ${reason}`));
    console.warn('Run the full suite to be sure: npm run unit');
}

main().then(
    (status) => process.exit(status),
    (error: Error) => {
        console.error(error.message);
        process.exit(1);
    },
);
