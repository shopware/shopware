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
import { camelize, capitalize } from 'vue';
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

const SELF_REFERENCE_SUFFIX = '__self';

function componentTag(name: string): string {
    return name.endsWith(SELF_REFERENCE_SUFFIX) ? name.slice(0, -SELF_REFERENCE_SUFFIX.length) : name;
}

/** The binding Vue's `resolveSetupReference()` selects for a template asset, in lookup order. */
function setupBinding(name: string, bindings: Readonly<Record<string, unknown>>): string | null {
    const camelName = camelize(name);
    const candidates = [
        name,
        camelName,
        capitalize(camelName),
    ];
    const dotIndex = name.indexOf('.');

    if (dotIndex > 0) {
        const namespace = name.slice(0, dotIndex);
        const camelNamespace = camelize(namespace);

        candidates.push(namespace, camelNamespace, capitalize(camelNamespace));
    }

    return candidates.find((candidate) => candidate in bindings) ?? null;
}

/**
 * Returns the first setup binding that changes a globally resolved component tag into a setup
 * reference. Normal-script imports are the one valid case: local components stay in that script
 * during the migration and `<script setup>` intentionally exposes them to the template.
 */
function componentBindingCollision(
    unboundComponents: string[],
    boundComponents: string[],
    bindings: Readonly<Record<string, unknown>>,
    imports: Readonly<Record<string, { isFromSetup: boolean }>>,
): string | null {
    const remaining = new Set(boundComponents.map(componentTag));

    for (const unresolved of unboundComponents) {
        const tag = componentTag(unresolved);

        if (remaining.has(tag)) {
            continue;
        }

        const binding = setupBinding(tag, bindings);

        if (binding === null) {
            continue;
        }

        const imported = imports[binding];
        const isBlockRuntimeTag = tag === 'sw-block' || tag === 'sw-block-parent';

        if (imported?.isFromSetup === false && !isBlockRuntimeTag) {
            continue;
        }

        return `binding '${binding}' shadows a component tag the template renders`;
    }

    return null;
}

/** Returns the first binding that changes a globally resolved directive into a setup reference. */
function directiveBindingCollision(
    unboundDirectives: string[],
    boundDirectives: string[],
    bindings: Readonly<Record<string, unknown>>,
    imports: Readonly<Record<string, { isFromSetup: boolean }>>,
): string | null {
    const remaining = new Set(boundDirectives);

    for (const directive of unboundDirectives) {
        if (remaining.has(directive)) {
            continue;
        }

        const binding = setupBinding(`v-${directive}`, bindings);

        if (binding === null || imports[binding]?.isFromSetup === false) {
            continue;
        }

        return `binding '${binding}' shadows a directive the template renders`;
    }

    return null;
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

    let script;

    try {
        script = compileScript(descriptor, { id: vuePath });
    } catch (error) {
        return errorMessage(error);
    }

    if (descriptor.template) {
        const unbound = compileTemplate({
            source: descriptor.template.content,
            filename: vuePath,
            id: vuePath,
        });

        if (unbound.errors.length > 0) {
            return errorMessage(unbound.errors[0]);
        }

        const bound = compileTemplate({
            source: descriptor.template.content,
            filename: vuePath,
            id: vuePath,
            compilerOptions: { bindingMetadata: script.bindings ?? {} },
        });

        if (bound.errors.length > 0) {
            return errorMessage(bound.errors[0]);
        }

        if (!unbound.ast || !bound.ast) {
            return 'Vue did not return a template AST for the generated SFC';
        }

        const collision = componentBindingCollision(
            unbound.ast.components,
            bound.ast.components,
            script.bindings ?? {},
            script.imports ?? {},
        );

        if (collision !== null) {
            return collision;
        }

        const directiveCollision = directiveBindingCollision(
            unbound.ast.directives,
            bound.ast.directives,
            script.bindings ?? {},
            script.imports ?? {},
        );

        if (directiveCollision !== null) {
            return directiveCollision;
        }
    }

    return null;
}

export { formatSfc, validateSfc };
