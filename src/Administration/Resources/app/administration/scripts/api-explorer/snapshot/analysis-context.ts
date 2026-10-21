/**
 * @sw-package framework
 */

import { createHash } from 'node:crypto';
import * as fs from 'node:fs';
import * as path from 'node:path';
import * as ts from 'typescript';

export interface AnalysisContext {
    /** Absolute */
    adminRoot: string;
    program: ts.Program;
    checker: ts.TypeChecker;
    /** Admin-root relative with '/' separators; files of the (possibly symlinked) node_modules start with 'node_modules/'. */
    toRelativePath(fileName: string): string;
    /**
     * Single line with collapsed whitespace; absolute `import("…")` paths become admin-root relative,
     * so the same code in two different roots prints identical type text.
     */
    normalizeTypeText(text: string): string;
    /** sha1 hex of the file's text, as read by the compiler host. */
    contentHash(sourceFile: ts.SourceFile): string;
    /** Absolute with '/' separators, sorted: every .ts and .js file below `src`, including specs, mocks and fixtures. */
    sourceTreeFiles: readonly string[];
}

interface CachedSourceFile {
    mtimeMs: number;
    ctimeMs: number;
    size: number;
    hash: string;
    languageKey: string;
    sourceFile: ts.SourceFile;
}

type SourceFileCache = Map<string, CachedSourceFile>;

const sourceFileCaches = new WeakMap<AnalysisContext, SourceFileCache>();

const contentHashes = new WeakMap<ts.SourceFile, string>();

const EXCLUDED_DIRECTORIES = new Set([
    'node_modules',
    '_mocks_',
    '__fixtures__',
]);

function sha1(text: string): string {
    return createHash('sha1').update(text).digest('hex');
}

function toPosix(fileName: string): string {
    return fileName.split(path.sep).join('/');
}

interface CollectedFiles {
    rootNames: string[];
    all: string[];
}

function collectSourceFiles(directory: string, collected: CollectedFiles, excluded = false): CollectedFiles {
    for (const entry of fs.readdirSync(directory, { withFileTypes: true })) {
        const fullPath = path.join(directory, entry.name);

        if (entry.isDirectory()) {
            if (entry.name !== 'node_modules') {
                collectSourceFiles(
                    fullPath,
                    collected,
                    excluded || EXCLUDED_DIRECTORIES.has(entry.name) || entry.name.endsWith('.spec'),
                );
            }
        } else if (/\.(ts|js)$/.test(entry.name)) {
            collected.all.push(toPosix(fullPath));

            if (!excluded && !entry.name.includes('.spec.')) {
                collected.rootNames.push(toPosix(fullPath));
            }
        }
    }

    return collected;
}

function readCompilerOptions(adminRoot: string): ts.ParsedCommandLine {
    const configFile = path.join(adminRoot, 'tsconfig.json');
    const result = ts.readConfigFile(configFile, (fileName) => ts.sys.readFile(fileName));

    if (result.error) {
        const message = ts.flattenDiagnosticMessageText(result.error.messageText, '\n');
        throw new Error(`Cannot read ${configFile}: ${message}`);
    }

    return ts.parseJsonConfigFileContent(result.config, ts.sys, adminRoot, undefined, configFile);
}

function toLanguageKey(languageVersionOrOptions: ts.ScriptTarget | ts.CreateSourceFileOptions): string {
    if (typeof languageVersionOrOptions !== 'object') {
        return String(languageVersionOrOptions);
    }

    // The options also carry the package.json scope, which is a new object per program.
    const { languageVersion, impliedNodeFormat, jsDocParsingMode } = languageVersionOrOptions;

    return `${languageVersion}|${impliedNodeFormat}|${jsDocParsingMode}`;
}

interface ResolutionWithLookups {
    resolvedModule?: { resolvedFileName: string };
    resolvedTypeReferenceDirective?: { resolvedFileName?: string };
    failedLookupLocations?: string[];
}

type ResolutionVisitor = (resolution: ResolutionWithLookups, name: string, mode: unknown, filePath: string) => void;

interface ProgramWithResolutions extends ts.Program {
    forEachResolvedModule?(callback: ResolutionVisitor): void;
    forEachResolvedTypeReferenceDirective?(callback: ResolutionVisitor): void;
}

/** Directory listings by path, valid while the directory's mtime, which moves on every added or removed entry, holds. */
type DirectoryCache = Map<string, { mtimeMs: number; entries: ReadonlySet<string> }>;

const directoryCaches = new WeakMap<AnalysisContext, DirectoryCache>();

function readDirectory(directory: string, cache: DirectoryCache): ReadonlySet<string> | null {
    try {
        const { mtimeMs } = fs.statSync(directory);
        const cached = cache.get(directory);

        if (cached?.mtimeMs === mtimeMs) {
            return cached.entries;
        }

        const entries = new Set(fs.readdirSync(directory));
        cache.set(directory, { mtimeMs, entries });

        return entries;
    } catch {
        cache.delete(directory);
        return null;
    }
}

/**
 * Without this the compiler keeps every resolution of an unchanged file, even when a file now exists where an
 * earlier lookup failed (`./u.ts` next to `./u/index.ts`) or the resolved file is gone.
 * Null when the old program does not expose its resolutions.
 */
function findInvalidatedResolutions(oldProgram: ts.Program, directoryCache: DirectoryCache): Set<string> | null {
    const internal = oldProgram as ProgramWithResolutions;

    if (
        typeof internal.forEachResolvedModule !== 'function' ||
        typeof internal.forEachResolvedTypeReferenceDirective !== 'function'
    ) {
        return null;
    }

    // Hundreds of thousands of lookups share a few thousand existing directories, so list those instead of probing each path.
    const listings = new Map<string, ReadonlySet<string> | null>();
    const listingOf = (directory: string): ReadonlySet<string> | null => {
        let listing = listings.get(directory);

        if (listing === undefined) {
            const parent = path.dirname(directory);
            listing =
                parent === directory || listingOf(parent)?.has(path.basename(directory))
                    ? readDirectory(directory, directoryCache)
                    : null;
            listings.set(directory, listing);
        }

        return listing;
    };
    const known = new Map<string, boolean>();
    const exists = (file: string): boolean => {
        let result = known.get(file);

        if (result === undefined) {
            result = listingOf(path.dirname(file))?.has(path.basename(file)) ?? false;
            known.set(file, result);
        }

        return result;
    };
    const invalidated = new Set<string>();
    const visit: ResolutionVisitor = (resolution, _name, _mode, filePath) => {
        if (invalidated.has(filePath)) {
            return;
        }

        const resolvedFile =
            resolution.resolvedModule?.resolvedFileName ?? resolution.resolvedTypeReferenceDirective?.resolvedFileName;

        if ((resolvedFile && !exists(resolvedFile)) || resolution.failedLookupLocations?.some(exists)) {
            invalidated.add(filePath);
        }
    };

    internal.forEachResolvedModule(visit);
    internal.forEachResolvedTypeReferenceDirective(visit);

    return invalidated;
}

function createCachingHost(
    options: ts.CompilerOptions,
    cache: SourceFileCache,
    invalidatedResolutions: ReadonlySet<string>,
): ts.CompilerHost {
    const baseHost = ts.createCompilerHost(options, true);

    return {
        ...baseHost,
        hasInvalidatedResolutions: (filePath) => invalidatedResolutions.has(filePath),
        getSourceFile(fileName, languageVersionOrOptions, onError, shouldCreateNewSourceFile) {
            const stat = fs.statSync(fileName, { throwIfNoEntry: false });

            if (!stat || shouldCreateNewSourceFile) {
                cache.delete(fileName);
                return baseHost.getSourceFile(fileName, languageVersionOrOptions, onError, shouldCreateNewSourceFile);
            }

            const languageKey = toLanguageKey(languageVersionOrOptions);
            const cached = cache.get(fileName);

            // ctime also moves on a permission change, which can make the file unreadable.
            if (
                cached?.languageKey === languageKey &&
                cached.mtimeMs === stat.mtimeMs &&
                cached.ctimeMs === stat.ctimeMs &&
                cached.size === stat.size
            ) {
                return cached.sourceFile;
            }

            const text = baseHost.readFile(fileName);

            if (text === undefined) {
                cache.delete(fileName);
                onError?.(`Cannot read ${fileName}`);
                return undefined;
            }

            const hash = sha1(text);
            const sourceFile =
                cached?.languageKey === languageKey && cached.hash === hash
                    ? cached.sourceFile
                    : ts.createSourceFile(fileName, text, languageVersionOrOptions, true);

            cache.set(fileName, {
                mtimeMs: stat.mtimeMs,
                ctimeMs: stat.ctimeMs,
                size: stat.size,
                hash,
                languageKey,
                sourceFile,
            });
            contentHashes.set(sourceFile, hash);

            return sourceFile;
        },
    };
}

function createPathMapper(adminRoot: string): (fileName: string) => string {
    const nodeModules = path.join(adminRoot, 'node_modules');
    const prefixes: Array<[string, string]> = [
        [
            nodeModules,
            'node_modules/',
        ],
        [
            adminRoot,
            '',
        ],
    ];

    for (const [
        directory,
        replacement,
    ] of [...prefixes]) {
        const realDirectory = fs.existsSync(directory) ? fs.realpathSync(directory) : directory;

        if (realDirectory !== directory) {
            prefixes.push([
                realDirectory,
                replacement,
            ]);
        }
    }

    const posixPrefixes = prefixes
        .map(([directory, replacement]): [string, string] => [`${toPosix(directory)}/`, replacement])
        .sort(([a], [b]) => b.length - a.length);

    return (fileName) => {
        const posixName = toPosix(fileName);
        const match = posixPrefixes.find(([prefix]) => posixName.startsWith(prefix));

        return match ? `${match[1]}${posixName.slice(match[0].length)}` : posixName;
    };
}

export function createAnalysisContext(adminRoot: string, options: { previous?: AnalysisContext } = {}): AnalysisContext {
    const root = path.resolve(adminRoot);
    const parsed = readCompilerOptions(root);
    const compilerOptions: ts.CompilerOptions = { ...parsed.options, checkJs: false, noEmit: true };
    const previous = options.previous?.adminRoot === root ? options.previous : undefined;
    const cache = (previous && sourceFileCaches.get(previous)) ?? new Map<string, CachedSourceFile>();

    const sourceRoot = `${toPosix(path.join(root, 'src'))}/`;
    const declarationFiles = parsed.fileNames.filter(
        (fileName) => fileName.endsWith('.d.ts') && !toPosix(fileName).startsWith(sourceRoot),
    );

    const sourceTree = collectSourceFiles(path.join(root, 'src'), { rootNames: [], all: [] });
    const directoryCache: DirectoryCache = (previous && directoryCaches.get(previous)) ?? (new Map() as DirectoryCache);
    const invalidatedResolutions = previous
        ? findInvalidatedResolutions(previous.program, directoryCache)
        : new Set<string>();
    const program = ts.createProgram({
        rootNames: [
            ...sourceTree.rootNames,
            ...declarationFiles,
        ],
        options: compilerOptions,
        host: createCachingHost(compilerOptions, cache, invalidatedResolutions ?? new Set()),
        oldProgram: invalidatedResolutions ? previous?.program : undefined,
    });

    const toRelativePath = createPathMapper(root);
    const context: AnalysisContext = {
        adminRoot: root,
        program,
        checker: program.getTypeChecker(),
        toRelativePath,
        normalizeTypeText: (text) =>
            text
                .replace(/import\((["'])(.+?)\1\)/g, (_match, quote: string, fileName: string) => {
                    return `import(${quote}${toRelativePath(fileName)}${quote})`;
                })
                .replace(/\s+/g, ' ')
                .trim(),
        contentHash: (sourceFile) => {
            let hash = contentHashes.get(sourceFile);

            if (hash === undefined) {
                hash = sha1(sourceFile.text);
                contentHashes.set(sourceFile, hash);
            }

            return hash;
        },
        sourceTreeFiles: sourceTree.all.sort(),
    };

    sourceFileCaches.set(context, cache);
    directoryCaches.set(context, directoryCache);

    return context;
}
