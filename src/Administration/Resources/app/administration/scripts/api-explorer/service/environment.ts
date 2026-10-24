/**
 * @sw-package framework
 */

import { createHash } from 'node:crypto';
import * as fs from 'node:fs';
import * as path from 'node:path';
import { listIgnoredDeclarationFiles, type MaterializedFile } from '../git';

export interface ExtractionEnvironment {
    /** Short hash; part of the cache key next to the commit and the extractor version. */
    key: string;
    /** Git-ignored declarations of the checkout, copied into every materialized ref. */
    extraFiles: MaterializedFile[];
}

const EXPLORER_ROOT = path.resolve(__dirname, '..');

/** The code that shapes an extracted snapshot: the extractor, the materialization of a ref and the component scan. */
export const EXTRACTOR_SOURCES = [
    path.join(EXPLORER_ROOT, 'snapshot'),
    path.join(EXPLORER_ROOT, 'git'),
    path.join(EXPLORER_ROOT, '../codemods/sfc-migration/component-source-model.ts'),
    path.join(EXPLORER_ROOT, '../codemods/sfc-migration/ast.ts'),
];

function collectSourceFiles(entry: string, files: string[]): void {
    const name = path.basename(entry);

    if (!fs.existsSync(entry) || name.startsWith('__') || name.endsWith('.spec') || name === 'test-helpers.ts') {
        return;
    }

    if (fs.statSync(entry).isDirectory()) {
        fs.readdirSync(entry)
            .sort()
            .forEach((child) => collectSourceFiles(path.join(entry, child), files));
    } else if (entry.endsWith('.ts') && !entry.includes('.spec.')) {
        files.push(entry);
    }
}

/** Covers code changes that were not accompanied by an `EXTRACTOR_VERSION` bump. */
export function hashExtractorSources(entries: readonly string[]): string {
    const files: string[] = [];
    entries.forEach((entry) => collectSourceFiles(entry, files));

    const hash = createHash('sha256');

    for (const file of files) {
        const content = fs.readFileSync(file);
        hash.update(`${path.relative(EXPLORER_ROOT, file)}\0${content.length}\0`).update(content);
    }

    return hash.digest('hex');
}

let extractorSourceHash: string | undefined;

/** What a ref snapshot depends on besides its commit: the extractor code, the admin path, ignored declarations and the installed packages. */
export async function collectEnvironment(
    repoRoot: string,
    adminRelativePath: string,
    extractorSources: string = (extractorSourceHash ??= hashExtractorSources(EXTRACTOR_SOURCES)),
): Promise<ExtractionEnvironment> {
    const extraFiles = (await listIgnoredDeclarationFiles(repoRoot, adminRelativePath)).map((file) => ({
        path: file,
        content: fs.readFileSync(path.join(repoRoot, ...file.split('/'))),
    }));
    const packageLock = path.join(repoRoot, adminRelativePath, 'package-lock.json');
    const hash = createHash('sha256').update(`extractor\0${extractorSources}\0admin\0${adminRelativePath}\0`);

    for (const file of extraFiles) {
        hash.update(`file\0${file.path}\0${file.content.length}\0`).update(file.content);
    }

    hash.update('package-lock\0').update(fs.existsSync(packageLock) ? fs.readFileSync(packageLock) : 'missing');

    return { key: hash.digest('hex').slice(0, 12), extraFiles };
}
