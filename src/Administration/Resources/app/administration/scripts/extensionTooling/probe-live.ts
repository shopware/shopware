/**
 * @sw-package framework
 *
 * Live mode resolution: asynchronous `tsc --showConfig` / `eslint --print-config`
 * runs against an extension's own config. This is the authority — it observes
 * what the tools actually resolve, including composition the static analysis in
 * `./probe-static` cannot see (a preset reached through node_modules, or a
 * factory composed indirectly via a second local file).
 *
 * Only the check command runs these; setup stays synchronous and renders the
 * static best guess. Nothing is cached between runs.
 */

import path from 'path';
import { runCommand } from './probe-command';
import { eslintConfigVerdict, tsconfigVerdict } from './probe-static';
import type { AdministrationTarget, OwnedConfig } from './shared';

/** Shown when a config-load failure produced no recognizable error line. */
export const ESLINT_LOAD_FAILED_DETAIL =
    'own ESLint config failed to load — run with --verbose for the underlying error (often an ESLint ' +
    'version or plugin-resolution mismatch).';

/** Shown when a config loads cleanly but the factory's runtime contract rule never applies. */
export const ESLINT_NOT_COMPOSED_DETAIL = 'own ESLint config does not compose the Shopware preset — see setup guidance.';

/**
 * Picks the actionable line from failed ESLint output. ESLint prefixes fatal
 * config-load errors with the generic banner `Oops! Something went wrong! :(`
 * and a version/usage preamble; surfacing that as the `why:` hides the real
 * cause behind `--verbose`. Prefer the first line that looks like a real
 * runtime error (an error class or an `ERR_*` code); fall back to a stable
 * message that names `--verbose` rather than repeating the banner.
 */
export function selectEslintErrorLine(output: string): string {
    const lines = output
        .split(/\r?\n/)
        .map((line) => line.trim())
        .filter((line) => line !== '');
    const errorLine = lines.find(
        (line) =>
            /^(Error|TypeError|ReferenceError|SyntaxError|RangeError|AggregateError|EvalError|URIError)\b/.test(line) ||
            /\bERR_[A-Z0-9_]+\b/.test(line),
    );

    return errorLine ?? ESLINT_LOAD_FAILED_DETAIL;
}

/**
 * Live probe: a custom tsconfig composes the Shopware preset when its
 * resolved configuration reaches the shipped type surface (directly or
 * through the generated bridge). `tsc --showConfig` resolves the whole
 * extends chain.
 */
export async function probeTsMode(
    target: AdministrationTarget,
    projectRoot: string,
    administrationRoot: string,
): Promise<OwnedConfig | null> {
    if (!target.tsconfig) {
        return target.tsconfig;
    }

    const relativePath = target.tsconfig.path;
    const tscPath = path.join(administrationRoot, 'node_modules', 'typescript', 'bin', 'tsc');
    const tsconfigPath = path.resolve(projectRoot, relativePath);
    const probe = await runCommand(
        process.execPath,
        [
            tscPath,
            '--showConfig',
            '--project',
            tsconfigPath,
        ],
        projectRoot,
    );

    if (probe.status !== 0) {
        const firstErrorLine =
            probe.output.split('\n').find((line) => line.trim() !== '') ?? 'the tsconfig does not resolve.';

        return { path: relativePath, composes: false, detail: firstErrorLine };
    }

    const composes = probe.output.includes('extension-tooling/admin-types') || probe.output.includes('admin-types.d.ts');

    if (composes) {
        return { path: relativePath, composes: true };
    }

    // The live probe is authoritative that it does not compose; borrow the
    // static verdict only for its human explanation.
    const verdict = tsconfigVerdict(tsconfigPath, relativePath);

    return verdict.composes
        ? { path: relativePath, composes: false, detail: 'the tsconfig does not reach the Shopware type surface.' }
        : verdict;
}

/**
 * Live probe: a custom ESLint config composes the Shopware preset when the
 * resolved configuration for a sample source file carries the factory's
 * runtime contract rule. (`--print-config` emits the merged config without
 * block names, so the probe checks for the rule instead.)
 */
export async function probeEslintMode(
    target: AdministrationTarget,
    projectRoot: string,
    administrationRoot: string,
    eslintBaseArguments: string[],
    sampleFile: string | null,
): Promise<OwnedConfig | null> {
    if (!target.eslintConfig) {
        return target.eslintConfig;
    }

    const relativePath = target.eslintConfig.path;

    if (!sampleFile) {
        return { path: relativePath, composes: true };
    }

    const eslintPath = path.join(administrationRoot, 'node_modules', 'eslint', 'bin', 'eslint.js');
    const probe = await runCommand(
        process.execPath,
        [
            eslintPath,
            ...eslintBaseArguments,
            '--print-config',
            sampleFile,
        ],
        projectRoot,
    );

    if (probe.status !== 0) {
        return { path: relativePath, composes: false, detail: selectEslintErrorLine(probe.output) };
    }

    if (probe.output.includes('plugin-rules/no-src-imports')) {
        return { path: relativePath, composes: true };
    }

    // The live probe is authoritative that it does not compose; borrow the
    // static verdict's explanation, falling back to a stable sentence.
    const verdict = eslintConfigVerdict(path.resolve(projectRoot, relativePath), relativePath);

    return { path: relativePath, composes: false, detail: verdict.detail ?? ESLINT_NOT_COMPOSED_DETAIL };
}
