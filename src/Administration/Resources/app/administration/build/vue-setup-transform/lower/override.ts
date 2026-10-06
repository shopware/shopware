/**
 * @sw-package framework
 */

/**
 * Lowers override Shopware setup scripts into hidden components that register setup overrides.
 *
 * The generated block stays a plain `<script setup>` whose body registers the override callback: the
 * hidden component mounts once at boot (sw-admin renders all registered override components in a
 * hidden container), which runs the registration and renders the template so `<sw-block extends>`
 * content is picked up. User code is preserved inside the callback, which returns the declared
 * replacements and the locals the template reads. The registration call returns one binding per name,
 * which the template is compiled against.
 *
 * The callback body is not re-indented - the transform does not beautify its output.
 */

import { fromSource, generated, type SourceChunk } from '../source-edits/chunks';
import type { SourceEdit } from '../source-edits/apply-source-edits';
import type { OverrideSetupScriptAnalysis } from '../script-analyzer';
import type { TemplateAnalysis } from '../template-analyzer';
import type { ShopwareSetupBlock } from '../utils/shopware-setup-block';
import { escapeSingleQuoted } from './shared';
import { transformRanges } from '../source-edits/transform-ranges';

/**
 * Lists the override locals its template can reach: every runtime binding that is not a declared
 * replacement, plus the runtime input aliases.
 */
function getOverrideLocalNames(block: ShopwareSetupBlock, analysis: OverrideSetupScriptAnalysis): string[] {
    if (!block.template) {
        return [];
    }

    const overrideEntries = new Set(analysis.overrideEntries);
    const bindingNames = analysis.runtimeBindings.map((binding) => binding.name);

    return Array.from(new Set([...bindingNames, ...analysis.runtimeInputAliasNames])).filter(
        (name) => !overrideEntries.has(name),
    );
}

/**
 * Builds the callback's return value: `override` replaces base state, `local` only feeds the template.
 */
function buildOverrideReturn(analysis: OverrideSetupScriptAnalysis, localNames: string[]): string {
    const group = (names: string[]) => (names.length > 0 ? `{ ${names.join(', ')} }` : '{}');

    return [
        'return {',
        `    override: ${group(analysis.overrideEntries)},`,
        `    local: ${group(localNames)},`,
        '};',
    ].join('\n');
}

/**
 * Builds the destructuring that receives the bindings the template is compiled against, one per name
 * the callback returns. Empty for an override without a template, which reads none.
 */
function buildBindingsDeclaration(block: ShopwareSetupBlock, analysis: OverrideSetupScriptAnalysis, localNames: string[]) {
    const names = block.template ? [...localNames, ...analysis.overrideEntries] : [];

    return names.length > 0 ? `const { ${names.join(', ')} } = ` : '';
}

/**
 * The generated attribute that binds an override `<sw-block extends>` to the component it overrides.
 *
 * Carries the target component name so the override registers against `componentName + blockName`, matching
 * the base block's own component name and mirroring how Twig identifies a `{% block %}` by its component.
 */
function toComponentNameEdit(at: number, componentName: string): SourceEdit {
    return {
        start: at,
        end: at,
        replacement: ` sw-internal-component-name='${escapeSingleQuoted(componentName)}'`,
    };
}

/**
 * Lowers override mode into a hidden override component consumed by
 * registerOverrideComponent.
 *
 * Emits the script content, the component name on every `<sw-block extends>`, and a generated
 * `<template>` when the override has none - the hidden component only registers its callback
 * once it mounts, and Vue warns about a component with neither template nor render function.
 */
function buildOverrideScript(
    block: ShopwareSetupBlock,
    analysis: OverrideSetupScriptAnalysis,
    templateAnalysis: TemplateAnalysis,
): SourceEdit[] {
    // Generated bindings use the reserved `__swSetup` prefix (rejected as user bindings), so they are
    // deterministic and never collide.
    const previousStateName = '__swSetupPreviousState';
    const propsName = '__swSetupProps';
    const contextName = '__swSetupContext';
    // The author body moves into a callback, so everything that cannot live in a function body leaves it:
    // imports are illegal there, an ambient `declare` describes a value from elsewhere, and the markers
    // are compile-time only. Imports and type declarations are re-emitted at the script root below.
    const callbackBody = transformRanges(block, [
        ...analysis.imports,
        ...analysis.typeDeclarations,
        ...analysis.markerStatements,
    ]);
    const chunks: SourceChunk[] = [generated('\n')];
    const localNames = getOverrideLocalNames(block, analysis);

    analysis.imports.forEach((importBlock) => {
        chunks.push(fromSource(block, importBlock));
        chunks.push(generated('\n'));
    });

    if (analysis.imports.length > 0) {
        chunks.push(generated('\n'));
    }

    const body = [
        generated(`const useSwPreviousState = () => ${previousStateName};\n`),
        generated(`const useSwProps = () => ${propsName};\n`),
        generated(`const useSwContext = () => ${contextName};\n\n`),
        ...callbackBody,
        generated(`\n\n${buildOverrideReturn(analysis, localNames)}`),
    ];

    analysis.typeDeclarations.forEach((typeDeclaration) => {
        chunks.push(fromSource(block, typeDeclaration));
        chunks.push(generated('\n'));
    });

    if (analysis.typeDeclarations.length > 0) {
        chunks.push(generated('\n'));
    }

    chunks.push(
        generated(
            `${buildBindingsDeclaration(block, analysis, localNames)}Shopware.Component.overrideComponentSetup()('${escapeSingleQuoted(block.componentName)}', (${previousStateName}, ${propsName}, ${contextName}) => {`,
        ),
        generated('\n'),
        ...body,
        generated('\n});\n'),
    );

    // Only emitted when the override brings no template of its own; the extension-targets registration
    // is emitted either way, so it cannot ride along on this branch.
    const placeholderTemplate = block.template
        ? ''
        : '<template><!-- Shopware override registration component --></template>\n';

    return [
        ...buildNativeExtensionTargetsEdits(block, templateAnalysis, placeholderTemplate),
        ...templateAnalysis.componentNameInsertions.map((at) => toComponentNameEdit(at, block.componentName)),
        {
            start: block.contentStart,
            end: block.contentEnd,
            replacement: chunks,
        },
    ];
}

/**
 * Builds the statement that registers this override's extension targets.
 *
 * Deliberately not part of `<script setup>`: that body only runs when the hidden override component
 * mounts, which is after `resolveComponentTemplates()`. Module-eval code runs during `loadPlugins()`,
 * so the registry is complete while the Twig template pipeline can still act on it.
 */
function buildNativeExtensionTargetsCall(block: ShopwareSetupBlock, templateAnalysis: TemplateAnalysis): string {
    const blockNames = Array.from(new Set(templateAnalysis.extendedBlockNames));
    const blocksProperty =
        blockNames.length > 0
            ? [
                  '    blocks: [',
                  ...blockNames.map((blockName) => `        '${escapeSingleQuoted(blockName)}',`),
                  '    ],',
              ]
            : [];

    return [
        // Optional call: this line is compiled into every shipped plugin bundle and runs at module
        // eval, so a missing function would abort the whole entry and take the plugin down with it.
        'Shopware.Component.registerNativeExtensionTargets?.({',
        `    component: '${escapeSingleQuoted(block.componentName)}',`,
        ...blocksProperty,
        '});',
        '',
    ].join('\n');
}

/**
 * Places the extension-targets registration, plus a generated template when the override has none.
 *
 * Vue allows exactly one plain `<script>` beside `<script setup>`, and the migration codemod already
 * spends it on its `data-sfc-migration-module` prelude - so the registration is appended to that block
 * where it exists, and only emitted as its own (with `lang` mirrored from the setup block) where it
 * does not.
 */
function buildNativeExtensionTargetsEdits(
    block: ShopwareSetupBlock,
    templateAnalysis: TemplateAnalysis,
    placeholderTemplate: string,
): SourceEdit[] {
    const registration = buildNativeExtensionTargetsCall(block, templateAnalysis);

    if (!block.moduleScript) {
        const langAttribute = block.lang ? ` lang="${block.lang}"` : '';

        // A single edit rather than two inserts at offset 0: two edits sharing a position would make the
        // emitted order depend on the sort stability of applySourceEdits.
        return [
            {
                start: 0,
                end: 0,
                replacement: `<script${langAttribute}>\n${registration}</script>\n${placeholderTemplate}`,
            },
        ];
    }

    // The codemod ends its prelude with a newline, but a hand-written one need not - without the newline
    // the call would land on the tail of the last statement. The leading semicolon terminates a prelude
    // whose last statement is left open: a dangling `export const value =` must stay the syntax error it
    // is, instead of silently receiving the registration call's return value.
    const separator = `${block.moduleScript.content.endsWith('\n') ? '' : '\n'};`;

    return [
        ...(placeholderTemplate.length > 0
            ? [
                  {
                      start: 0,
                      end: 0,
                      replacement: placeholderTemplate,
                  },
              ]
            : []),
        {
            start: block.moduleScript.contentEnd,
            end: block.moduleScript.contentEnd,
            replacement: `${separator}${registration}`,
        },
    ];
}

/**
 * @private
 */
export { buildOverrideScript };
