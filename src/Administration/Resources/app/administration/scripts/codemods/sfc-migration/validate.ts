/**
 * @sw-package framework
 */

/** The safety gate: the real build transform AND Vue's compiler must accept a generated SFC. */

import { transformShopwareSetupSfc } from '../../../build/vue-setup-transform';
import { parse, compileScript, compileTemplate } from '@vue/compiler-sfc';
import { camelize, capitalize } from 'vue';
import { errorText } from './ast';
// The standalone API with explicitly imported plugins is required: prettier's main entry loads its
// implementation through dynamic import(), which Jest's CJS sandbox rejects.
import { format } from 'prettier/standalone';
import * as prettierPluginHtml from 'prettier/plugins/html';
import * as prettierPluginBabel from 'prettier/plugins/babel';
import * as prettierPluginEstree from 'prettier/plugins/estree';
import * as prettierPluginTypescript from 'prettier/plugins/typescript';
import * as prettierPluginPostcss from 'prettier/plugins/postcss';

// Mirrors .prettierrc.js, whose loading needs that dynamic import() too, and so does
// prettier-plugin-multiline-arrays; the project's prettier check does not cover .vue files.
const PRETTIER_OPTIONS: Parameters<typeof format>[1] = {
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
    return errorText(error).split('\n')[0].trim();
}

/** Prettier owns the indentation of the output and doubles as a syntax pre-check. */
async function formatSfc(sfc: string): Promise<string> {
    return format(sfc, { ...PRETTIER_OPTIONS, parser: 'vue' });
}

async function formatModule(source: string, lang: 'js' | 'ts'): Promise<string> {
    return format(source, { ...PRETTIER_OPTIONS, parser: lang === 'ts' ? 'typescript' : 'babel' });
}

const SELF_REFERENCE_SUFFIX = '__self';

function componentTag(name: string): string {
    return name.endsWith(SELF_REFERENCE_SUFFIX) ? name.slice(0, -SELF_REFERENCE_SUFFIX.length) : name;
}

/** The binding Vue's `resolveSetupReference()` selects for a template asset, in lookup order. */
function setupBinding(name: string, bindings: Readonly<Record<string, unknown>>): string | null {
    const camelName = camelize(name);
    const candidates = [name, camelName, capitalize(camelName)];
    const dotIndex = name.indexOf('.');

    if (dotIndex > 0) {
        const namespace = name.slice(0, dotIndex);
        const camelNamespace = camelize(namespace);

        candidates.push(namespace, camelNamespace, capitalize(camelNamespace));
    }

    return candidates.find((candidate) => candidate in bindings) ?? null;
}

type ImportRecord = Readonly<Record<string, { source: string }>>;

/**
 * An author import may provide a tag or directive: a local component the `components` option
 * registered keeps resolving through it. A sibling-module import was a plain module binding before,
 * which the template never saw.
 */
function isAuthorImport(binding: string, imports: ImportRecord, moduleSpecifier: string | undefined): boolean {
    return imports[binding] !== undefined && imports[binding].source !== moduleSpecifier;
}

function componentBindingCollision(
    unboundComponents: string[],
    boundComponents: string[],
    bindings: Readonly<Record<string, unknown>>,
    imports: ImportRecord,
    moduleSpecifier: string | undefined,
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

        const isBlockRuntimeTag = tag === 'sw-block' || tag === 'sw-block-parent';

        if (isAuthorImport(binding, imports, moduleSpecifier) && !isBlockRuntimeTag) {
            continue;
        }

        return `binding '${binding}' shadows a component tag the template renders`;
    }

    return null;
}

function directiveBindingCollision(
    unboundDirectives: string[],
    boundDirectives: string[],
    bindings: Readonly<Record<string, unknown>>,
    imports: ImportRecord,
    moduleSpecifier: string | undefined,
): string | null {
    const remaining = new Set(boundDirectives);

    for (const directive of unboundDirectives) {
        if (remaining.has(directive)) {
            continue;
        }

        const binding = setupBinding(`v-${directive}`, bindings);

        if (binding === null || isAuthorImport(binding, imports, moduleSpecifier)) {
            continue;
        }

        return `binding '${binding}' shadows a directive the template renders`;
    }

    return null;
}

/** The first error, or `null`. `vuePath` must be the real target: the transform infers the name from it. */
function validateSfc(sfc: string, vuePath: string, moduleSpecifier?: string): string | null {
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
            moduleSpecifier,
        );

        if (collision !== null) {
            return collision;
        }

        const directiveCollision = directiveBindingCollision(
            unbound.ast.directives,
            bound.ast.directives,
            script.bindings ?? {},
            script.imports ?? {},
            moduleSpecifier,
        );

        if (directiveCollision !== null) {
            return directiveCollision;
        }
    }

    return null;
}

export { formatModule, formatSfc, validateSfc };
