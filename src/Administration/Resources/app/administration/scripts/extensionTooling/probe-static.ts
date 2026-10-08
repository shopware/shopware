/**
 * @sw-package framework
 *
 * Static verdicts for extension-owned configs: does the config compose the
 * generated bridge (or the shipped preset directly), and if not, why. No
 * process spawns, so this is safe to run during setup.
 *
 * Knowingly lossy: a bare package specifier in `extends` is not followed, and
 * the ESLint side is a text scan, so composition reached indirectly through a
 * second local file reads as a false negative. Resolving what the tools
 * actually see needs a live probe.
 */

import fs from 'fs';
import path from 'path';
import ts from 'typescript';
import { SHIM_DIR_NAME, toPosix } from './shared';
import type { OwnedConfig } from './shared';

/**
 * The scaffolded config extends the bridge directly; the extra hops cover a
 * plugin's own base config between its leaf config and the bridge.
 */
const MAX_EXTENDS_DEPTH = 3;

/** Own path aliases are the one legitimate reason to declare `paths` — point at the file the bridge merges. */
const ALIASES_NOTE = ' Own path aliases? Declare them in tsconfig.aliases.json next to your config.';

function isPresetPath(configPath: string): boolean {
    const posixPath = toPosix(configPath);

    return posixPath.includes(`/${SHIM_DIR_NAME}/`) || posixPath.endsWith('extension-tooling/tsconfig.base.json');
}

function parseTsconfig(configPath: string): { config?: Record<string, unknown>; error?: string } {
    let text: string;

    try {
        text = fs.readFileSync(configPath, 'utf8');
    } catch (error) {
        return { error: error instanceof Error ? error.message : String(error) };
    }

    const parsed = ts.parseConfigFileTextToJson(configPath, text);

    if (parsed.error) {
        return { error: ts.flattenDiagnosticMessageText(parsed.error.messageText, ' ') };
    }

    return { config: parsed.config as Record<string, unknown> };
}

/**
 * Resolves a local `extends` specifier to an absolute path, applying tsconfig's
 * implicit `.json` extension. Returns null for bare package specifiers — a
 * preset reached through node_modules is not this tool's contract.
 */
function resolveLocalExtends(fromConfigPath: string, specifier: unknown): string | null {
    if (typeof specifier !== 'string' || !specifier.startsWith('.')) {
        return null;
    }

    const resolved = path.resolve(path.dirname(fromConfigPath), specifier);

    if (!fs.existsSync(resolved) && fs.existsSync(`${resolved}.json`)) {
        return `${resolved}.json`;
    }

    return resolved;
}

/** Whether the config's local `extends` chain reaches the preset. The depth cap also terminates a cyclic chain. */
function reachesPreset(configPath: string, config: Record<string, unknown>, depth = 1): boolean {
    if (depth > MAX_EXTENDS_DEPTH) {
        return false;
    }

    const specifiers = Array.isArray(config.extends) ? config.extends : [config.extends];

    return specifiers.some((specifier) => {
        const parentPath = resolveLocalExtends(configPath, specifier);

        if (parentPath === null) {
            return false;
        }

        if (isPresetPath(parentPath)) {
            return true;
        }

        const parent = parseTsconfig(parentPath).config;

        return parent !== undefined && reachesPreset(parentPath, parent, depth + 1);
    });
}

/**
 * Whether the config or anything in its local `extends` chain declares `key`.
 * Both `files` and `include` are inherited through `extends`, so a plugin base
 * config declaring one counts for the leaf that extends it.
 */
function chainDeclares(configPath: string, config: Record<string, unknown>, key: 'files' | 'include', depth = 1): boolean {
    if (config[key] !== undefined) {
        return true;
    }

    if (depth > MAX_EXTENDS_DEPTH) {
        return false;
    }

    const specifiers = Array.isArray(config.extends) ? config.extends : [config.extends];

    return specifiers.some((specifier) => {
        const parentPath = resolveLocalExtends(configPath, specifier);

        if (parentPath === null) {
            return false;
        }

        const parent = parseTsconfig(parentPath).config;

        return parent !== undefined && chainDeclares(parentPath, parent, key, depth + 1);
    });
}

/** Static verdict for an extension-owned tsconfig, at `relativePath` in the report's namespace. */
export function tsconfigVerdict(absolutePath: string, relativePath: string): OwnedConfig {
    const { config, error } = parseTsconfig(absolutePath);

    if (error || !config) {
        return {
            path: relativePath,
            composes: false,
            detail: error ?? 'the tsconfig does not resolve.',
            reason: 'unreadable',
        };
    }

    const aliasesNote = (config.compilerOptions as { paths?: unknown } | undefined)?.paths ? ALIASES_NOTE : '';

    if (!reachesPreset(absolutePath, config)) {
        return {
            path: relativePath,
            composes: false,
            detail:
                'the extends chain does not reach the Shopware preset or a generated ' +
                `${SHIM_DIR_NAME}/ bridge.${aliasesNote}`,
            reason: 'extends-missing',
        };
    }

    if (config.files !== undefined) {
        return {
            path: relativePath,
            composes: false,
            detail:
                'the tsconfig declares its own "files" array, which replaces the bridge\'s ' +
                `(tsconfig extends semantics) — admin-types.d.ts never enters the program.${aliasesNote}`,
            reason: 'files-override',
        };
    }

    // TypeScript only applies its default `**/*` glob when neither `files` nor
    // `include` is set anywhere in the chain. The bridge sets `files` for the
    // type surface, so a config inheriting it and declaring no `include` has a
    // program of exactly that one .d.ts — every source silently unchecked,
    // which surfaces downstream as a coverage tooling error rather than as the
    // one-line config defect it is.
    if (!chainDeclares(absolutePath, config, 'include') && chainDeclares(absolutePath, config, 'files')) {
        return {
            path: relativePath,
            composes: false,
            detail:
                'the tsconfig declares no "include", and the "files" inherited from the bridge suppresses ' +
                `TypeScript's default "**/*" glob — only admin-types.d.ts enters the program, none of the ` +
                `extension's own sources.${aliasesNote}`,
            reason: 'include-missing',
        };
    }

    return { path: relativePath, composes: true };
}

/** Static verdict for an extension-owned ESLint config, at `relativePath` in the report's namespace. */
export function eslintConfigVerdict(absolutePath: string, relativePath: string): OwnedConfig {
    let text = '';

    try {
        text = fs.readFileSync(absolutePath, 'utf8');
    } catch {
        // An unreadable config composes nothing; the detail below covers it.
    }

    if (text.includes(`${SHIM_DIR_NAME}/eslint.mjs`) || text.includes('extension-tooling/eslint.mjs')) {
        return { path: relativePath, composes: true };
    }

    return {
        path: relativePath,
        composes: false,
        detail: 'the config does not compose the Shopware factory, so the preset rules never apply.',
        reason: 'factory-missing',
    };
}
