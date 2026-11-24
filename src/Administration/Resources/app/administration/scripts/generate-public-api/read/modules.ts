/**
 * @sw-package framework
 *
 * The script files of a source tree as modules: parses a file, and finds the file an import names.
 */
import ts from 'typescript';
import type { SourceTree } from '../source/source-tree';

const MODULE_SUFFIXES = [
    '',
    '.ts',
    '.js',
    '/index.ts',
    '/index.js',
];

const SCRIPT_FILE = /\.(?:js|ts)$/;

/** @private */
export function isScriptFile(file: string): boolean {
    return SCRIPT_FILE.test(file);
}

/** @private */
export function scriptLanguage(file: string): 'js' | 'ts' {
    return file.endsWith('.ts') ? 'ts' : 'js';
}

/**
 * @private
 *
 * `src/app/service/acl.service.ts` gives `src/app/service/acl.service`.
 */
export function withoutScriptExtension(file: string): string {
    return file.replace(SCRIPT_FILE, '');
}

/** @private */
export function parseScript(file: string, tree: SourceTree): ts.SourceFile {
    return ts.createSourceFile(file, tree.read(file), ts.ScriptTarget.Latest, true);
}

function directory(file: string): string {
    return file.slice(0, file.lastIndexOf('/'));
}

/**
 * @private
 *
 * The source file behind a relative or `src/` import, `undefined` when the tree has none, as for a package.
 */
export function findModule(specifier: string, importer: string, tree: SourceTree): string | undefined {
    if (!specifier.startsWith('src/') && !specifier.startsWith('.')) {
        return undefined;
    }

    const base = specifier.startsWith('src/')
        ? specifier
        : new URL(specifier, `file:///${directory(importer)}/`).pathname.slice(1);

    return MODULE_SUFFIXES.map((suffix) => `${base}${suffix}`).find((file) => tree.has(file));
}

/**
 * @private
 *
 * The source file behind an import that has to name one.
 */
export function resolveModule(specifier: string, importer: string, tree: SourceTree): string {
    const resolved = findModule(specifier, importer, tree);

    if (resolved === undefined) {
        throw new Error(`${importer}: the import '${specifier}' matches no source file.`);
    }

    return resolved;
}
