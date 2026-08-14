/**
 * @sw-package framework
 */

/**
 * The codemod's safety gate: a generated SFC is only accepted when the real build transform lowers
 * it without complaint AND Vue's own compiler accepts the lowered output. This replaces hand-written
 * edge-case handling (e.g. v-if/v-else chains that break across block boundaries) — files the
 * toolchain would reject are reported instead of written.
 */

// The '.ts' specifier is required: a bare '../index' resolves to the CJS jiti bridge under Jest,
// which bypasses the transform pipeline (see build/vue-setup-transform/index.spec/helpers.ts).

import { transformShopwareSetupSfc } from '../../../build/vue-setup-transform/index.ts';
import { parse, compileScript, compileTemplate } from '@vue/compiler-sfc';
// The standalone API with explicitly imported plugins is required: prettier's main entry loads its
// implementation through dynamic import(), which Jest's CJS sandbox rejects.
import { format } from 'prettier/standalone';
import * as prettierPluginHtml from 'prettier/plugins/html';
import * as prettierPluginBabel from 'prettier/plugins/babel';
import * as prettierPluginEstree from 'prettier/plugins/estree';
import * as prettierPluginTypescript from 'prettier/plugins/typescript';
import * as prettierPluginPostcss from 'prettier/plugins/postcss';

// Mirrors .prettierrc.js; inlined because resolving the config file would need the same dynamic
// import() the standalone API exists to avoid. prettier-plugin-multiline-arrays is left out for
// the same reason (it requires prettier's dynamic-import entry at load time); the project's
// prettier check only covers .js/.ts, so generated .vue files cannot drift against it.
const PRETTIER_OPTIONS: Parameters<typeof format>[1] = {
    parser: 'vue',
    singleQuote: true,
    tabWidth: 4,
    printWidth: 125,
    trailingComma: 'all',
    plugins: [
        prettierPluginHtml,
        prettierPluginBabel,
        prettierPluginEstree,
        prettierPluginTypescript,
        prettierPluginPostcss,
    ],
};

// First line only: Vue compiler errors append multi-line code frames that would flood the report.
function errorMessage(error: unknown): string {
    return (error instanceof Error ? error.message : String(error)).split('\n')[0].trim();
}

/** Prettier owns all indentation of the assembled output and doubles as a syntax pre-check. */
async function formatSfc(sfc: string): Promise<string> {
    return format(sfc, PRETTIER_OPTIONS);
}

/**
 * Returns `null` when the SFC survives the full toolchain, otherwise the first error message.
 * The filename must be the real target path — the transform infers mode and component name from it.
 */
function validateSfc(sfc: string, vuePath: string): string | null {
    let lowered;

    try {
        lowered = transformShopwareSetupSfc(sfc, vuePath);
    } catch (error) {
        return errorMessage(error);
    }

    if (lowered === null) {
        return 'Vue could not parse the generated SFC';
    }

    const { descriptor, errors } = parse(lowered.code, { filename: vuePath });

    if (errors.length > 0) {
        return errorMessage(errors[0]);
    }

    try {
        compileScript(descriptor, { id: vuePath });
    } catch (error) {
        return errorMessage(error);
    }

    if (descriptor.template) {
        const compiled = compileTemplate({
            source: descriptor.template.content,
            filename: vuePath,
            id: vuePath,
        });

        if (compiled.errors.length > 0) {
            return errorMessage(compiled.errors[0]);
        }
    }

    return null;
}

export { formatSfc, validateSfc };
