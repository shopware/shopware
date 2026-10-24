/**
 * @sw-package framework
 */

import { createHash } from 'node:crypto';
import * as fs from 'node:fs';
import * as path from 'node:path';
import * as ts from 'typescript';
import type { AnalysisContext } from './analysis-context';
import { EXTRACTOR_VERSION } from './types';

/** Content hashes of files outside the program, e.g. templates; absolute path as key, reused while mtime and size match. */
export type FileHashCache = Map<string, { mtimeMs: number; ctimeMs: number; size: number; hash: string }>;

export interface DependencyFingerprints {
    /**
     * Hash over the extractor setup, `inputs`, and the paths and contents of `files`, their transitive imports
     * and every file that declares something globally (plus their imports). Files outside the program count by content.
     */
    of(files: readonly string[], inputs: string): string;
}

interface InternalSourceFile extends ts.SourceFile {
    imports?: readonly ts.StringLiteralLike[];
    moduleAugmentations?: ReadonlyArray<ts.StringLiteral | ts.Identifier>;
}

interface InternalProgram extends ts.Program {
    getResolvedModule?(
        file: ts.SourceFile,
        moduleName: string,
        mode: ts.ResolutionMode,
    ): ts.ResolvedModuleWithFailedLookupLocations | undefined;
}

interface Dependencies {
    sourceFiles: ts.SourceFile[];
    /** Absolute paths of files outside the program, or `?<specifier>` for an unresolved bare specifier. */
    others: string[];
}

/** Statements of a script file that cannot declare anything in the global scope. */
function isPlainCall(statement: ts.Statement): boolean {
    if (!ts.isExpressionStatement(statement)) {
        return false;
    }

    let expression = statement.expression;

    while (ts.isParenthesizedExpression(expression)) {
        expression = expression.expression;
    }

    return ts.isCallExpression(expression);
}

function isAugmentation(statement: ts.Statement): boolean {
    return (
        ts.isModuleDeclaration(statement) &&
        (ts.isStringLiteral(statement.name) || (statement.flags & ts.NodeFlags.GlobalAugmentation) !== 0)
    );
}

/** In a JS script these declare global types from a comment, also above a plain call. */
const JSDOC_TYPE_TAG = /@(?:typedef|callback|template)\b/;

/**
 * `all`: the file's imports may shape what it declares globally. `declarations`: a script whose top-level
 * calls declare nothing, so only module references inside its other statements matter.
 */
function globalRole(sourceFile: ts.SourceFile): 'all' | 'declarations' | null {
    if (sourceFile.isDeclarationFile) {
        return 'all';
    }

    if (sourceFile.fileName.endsWith('.json')) {
        return null;
    }

    if (ts.isExternalModule(sourceFile)) {
        return sourceFile.statements.some(isAugmentation) ? 'all' : null;
    }

    if (/\.[cm]?jsx?$/.test(sourceFile.fileName) && JSDOC_TYPE_TAG.test(sourceFile.text)) {
        return 'all';
    }

    return sourceFile.statements.every(isPlainCall) ? null : 'declarations';
}

function sha1(text: string | Buffer): string {
    return createHash('sha1').update(text).digest('hex');
}

/** An unreadable file gets a marker of its own, so its component is extracted again and reports the problem. */
function hashOtherFile(file: string, cache: FileHashCache): string {
    try {
        const stat = fs.statSync(file, { throwIfNoEntry: false });

        if (!stat?.isFile()) {
            cache.delete(file);
            return 'missing';
        }

        const cached = cache.get(file);

        // A permission change only moves ctime.
        if (cached?.mtimeMs === stat.mtimeMs && cached.ctimeMs === stat.ctimeMs && cached.size === stat.size) {
            return cached.hash;
        }

        const hash = sha1(fs.readFileSync(file));
        cache.set(file, { mtimeMs: stat.mtimeMs, ctimeMs: stat.ctimeMs, size: stat.size, hash });

        return hash;
    } catch (error) {
        cache.delete(file);
        return `unreadable:${(error as NodeJS.ErrnoException).code ?? 'unknown'}`;
    }
}

/** Null when the compiler internals it reads are unavailable; callers then extract everything. */
export function createDependencyFingerprints(
    context: AnalysisContext,
    fileHashes: FileHashCache,
): DependencyFingerprints | null {
    const program = context.program as InternalProgram;

    if (typeof program.getResolvedModule !== 'function') {
        return null;
    }

    const getResolvedModule = program.getResolvedModule.bind(program);
    const baseUrl = program.getCompilerOptions().baseUrl ?? context.adminRoot;
    const dependencyCache = new Map<ts.SourceFile, Dependencies>();

    const toOtherFile = (specifier: string, from: ts.SourceFile): string => {
        const cleaned = specifier.replace(/\?.*$/, '');

        if (cleaned.startsWith('.') || path.isAbsolute(cleaned)) {
            return path.resolve(path.dirname(from.fileName), cleaned);
        }

        const fromBaseUrl = path.resolve(baseUrl, cleaned);

        return fs.existsSync(fromBaseUrl) ? fromBaseUrl : `?${specifier}`;
    };

    const dependenciesOf = (sourceFile: InternalSourceFile, within?: ReadonlyArray<ts.Statement>): Dependencies => {
        const references = [
            ...(sourceFile.imports ?? []),
            ...(sourceFile.moduleAugmentations ?? []),
        ].filter(
            (reference): reference is ts.StringLiteralLike =>
                ts.isStringLiteralLike(reference) &&
                (!within || within.some((statement) => reference.pos >= statement.pos && reference.end <= statement.end)),
        );
        const dependencies: Dependencies = { sourceFiles: [], others: [] };

        for (const reference of references) {
            const mode = program.getModeForUsageLocation(sourceFile, reference);
            const resolved = getResolvedModule(sourceFile, reference.text, mode)?.resolvedModule;
            const target = resolved ? program.getSourceFile(resolved.resolvedFileName) : undefined;

            if (target) {
                dependencies.sourceFiles.push(target);
            } else {
                dependencies.others.push(resolved?.resolvedFileName ?? toOtherFile(reference.text, sourceFile));
            }
        }

        return dependencies;
    };

    const allDependencies = (sourceFile: ts.SourceFile): Dependencies => {
        let dependencies = dependencyCache.get(sourceFile);

        if (!dependencies) {
            dependencies = dependenciesOf(sourceFile);
            dependencyCache.set(sourceFile, dependencies);
        }

        return dependencies;
    };

    const entryOf = (sourceFile: ts.SourceFile): string =>
        `${context.toRelativePath(sourceFile.fileName)}\0${context.contentHash(sourceFile)}`;
    const otherEntryOf = (file: string): string =>
        file.startsWith('?') ? file : `${context.toRelativePath(file)}\0${hashOtherFile(file, fileHashes)}`;

    /** Expands every reached file fully, except those in `stop`; `start` supplies the first files' dependencies. */
    const collectClosure = (
        start: Array<[ts.SourceFile, Dependencies]>,
        stop: ReadonlySet<ts.SourceFile>,
    ): { sourceFiles: Set<ts.SourceFile>; others: Set<string> } => {
        const sourceFiles = new Set<ts.SourceFile>();
        const others = new Set<string>();
        const pending: Array<[ts.SourceFile, Dependencies | null]> = [...start];

        while (pending.length > 0) {
            const [sourceFile, given] = pending.pop()!;

            if (sourceFiles.has(sourceFile) || (!given && stop.has(sourceFile))) {
                continue;
            }

            sourceFiles.add(sourceFile);

            const dependencies = given ?? allDependencies(sourceFile);
            dependencies.others.forEach((file) => others.add(file));
            dependencies.sourceFiles.forEach((dependency) => pending.push([dependency, null]));
        }

        return { sourceFiles, others };
    };

    const roots: Array<[ts.SourceFile, Dependencies]> = [];

    for (const sourceFile of program.getSourceFiles()) {
        const role = globalRole(sourceFile);

        if (role === 'all') {
            roots.push([sourceFile, allDependencies(sourceFile)]);
        } else if (role === 'declarations') {
            roots.push([
                sourceFile,
                dependenciesOf(
                    sourceFile,
                    sourceFile.statements.filter((statement) => !isPlainCall(statement)),
                ),
            ]);
        }
    }

    const globalClosure = collectClosure(roots, new Set());
    // A script reached through its declarations was not expanded fully, so a component that reaches it expands it again.
    const fullyExpanded = new Set(
        [...globalClosure.sourceFiles].filter((sourceFile) => globalRole(sourceFile) !== 'declarations'),
    );
    const setupHash = sha1(
        [
            EXTRACTOR_VERSION,
            ts.version,
            JSON.stringify(program.getCompilerOptions()),
            ...[...globalClosure.sourceFiles].map(entryOf),
            ...[...globalClosure.others].map(otherEntryOf),
        ]
            .sort()
            .join('\n'),
    );

    return {
        of(files, inputs) {
            const start: Array<[ts.SourceFile, Dependencies]> = [];
            const otherFiles: string[] = [];

            for (const file of files) {
                const sourceFile = program.getSourceFile(file);

                if (sourceFile) {
                    start.push([sourceFile, allDependencies(sourceFile)]);
                } else {
                    otherFiles.push(file);
                }
            }

            const closure = collectClosure(start, fullyExpanded);
            const entries = [
                ...[...closure.sourceFiles].map(entryOf),
                ...[
                    ...otherFiles,
                    ...closure.others,
                ].map(otherEntryOf),
            ];

            return sha1([setupHash, inputs, ...new Set(entries)].sort().join('\n'));
        },
    };
}
