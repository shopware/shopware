/**
 * @sw-package framework
 */

/**
 * Lowers override Shopware setup scripts into hidden components that register setup overrides.
 *
 * The generated block stays a plain `<script setup>` whose body registers the override callback: the
 * hidden component mounts once at boot (sw-admin renders all registered override components in a
 * hidden container), which runs the registration and renders the template so `<sw-block extends>`
 * content is picked up. User code is preserved inside the callback and only declared replacements
 * plus template-used private locals are returned, namespaced per file.
 *
 * The callback body is not re-indented - the transform does not beautify its output.
 */

import { fromSource, generated, type SourceChunk } from '../source-edits/chunks';
import type { SourceEdit } from '../source-edits/apply-source-edits';
import type { OverrideSetupScriptAnalysis } from '../script-analyzer';
import type { OverrideSlotScope, TemplateAnalysis } from '../template-analyzer';
import type { ShopwareSetupBlock } from '../utils/shopware-setup-block';
import { escapeSingleQuoted } from './shared';
import { OVERRIDE_NAMESPACE_BINDING } from '../script-analyzer/macros';
import { transformRanges } from '../source-edits/transform-ranges';

/**
 * Builds the override callback payload from declared replacements and template-used private aliases.
 */
function buildOverrideReturn(analysis: OverrideSetupScriptAnalysis, overridePrivateBindings: Set<string>): string {
    const privateBindings = Array.from(overridePrivateBindings);

    if (analysis.overrideEntries.length === 0 && privateBindings.length === 0) {
        return 'return {};';
    }

    const lines = [
        'return {',
        ...analysis.overrideEntries.map((property) => `    ${property},`),
    ];

    if (privateBindings.length > 0) {
        lines.push(
            '    __swOverride: {',
            `        [${OVERRIDE_NAMESPACE_BINDING}]: {`,
            ...privateBindings.map((localName) => `            ${localName},`),
            '        },',
            '    },',
        );
    }

    lines.push('};');

    return lines.join('\n');
}

/**
 * The generated `#default` slot scope that carries override-local bindings into `<sw-block extends>`
 * content.
 *
 * Declared override bindings destructure under their own name; everything else the content reads goes
 * through the module's namespace symbol, emitted as a **computed** key so the pattern destructures by
 * that Symbol rather than by a literal name. Authoring `#default` on `<sw-block>` is rejected, so there
 * is never a user pattern to merge with.
 */
function toSlotScopeEdit(scope: OverrideSlotScope): SourceEdit {
    const mappings = [
        ...(scope.privateNames.length > 0
            ? [`__swOverride: { [${OVERRIDE_NAMESPACE_BINDING}]: { ${scope.privateNames.join(', ')} } }`]
            : []),
        ...scope.publicNames,
    ];

    return {
        start: scope.at,
        end: scope.at,
        replacement: ` #default="{ ${mappings.join(', ')} }"`,
    };
}

/**
 * Lowers override mode into a hidden override component consumed by
 * registerOverrideComponent.
 *
 * Emits the script content, one slot scope per `<sw-block extends>` that forwards bindings, and a
 * generated `<template>` when the override has none - the hidden component only registers its callback
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
        generated(`\n\n${buildOverrideReturn(analysis, templateAnalysis.privateBindings)}`),
    ];

    // Only needed when this override actually forwards private locals into a <sw-block extends> scope;
    // an override that only replaces public bindings has nothing to file under the namespace.
    //
    // Declared at module root, NOT inside the callback: the callback runs once per base-component
    // instance, so a symbol created there would be a different value every time and the state lookup
    // would never match. Module scope evaluates once, giving one stable symbol per override file - and it
    // stays template-visible, so the generated computed key resolves.
    if (templateAnalysis.privateBindings.size > 0) {
        chunks.push(
            generated(
                `const ${OVERRIDE_NAMESPACE_BINDING} = Symbol('${escapeSingleQuoted(block.componentName)}.override');\n\n`,
            ),
        );
    }

    analysis.typeDeclarations.forEach((typeDeclaration) => {
        chunks.push(fromSource(block, typeDeclaration));
        chunks.push(generated('\n'));
    });

    if (analysis.typeDeclarations.length > 0) {
        chunks.push(generated('\n'));
    }

    chunks.push(
        generated(
            `Shopware.Component.overrideComponentSetup()('${escapeSingleQuoted(block.componentName)}', (${previousStateName}, ${propsName}, ${contextName}) => {`,
        ),
        generated('\n'),
        ...body,
        generated('\n});\n'),
    );

    // Only emitted when the override brings no template of its own; the extension-targets script below
    // is emitted either way, so it cannot ride along on this branch.
    const placeholderTemplate = block.template
        ? ''
        : '<template><!-- Shopware override registration component --></template>\n';

    // A single edit rather than two inserts at offset 0: two edits sharing a position would make the
    // emitted order depend on the sort stability of applySourceEdits.
    const scriptPrefix: SourceEdit[] = [
        {
            start: 0,
            end: 0,
            replacement: buildNativeExtensionTargetsScript(block, templateAnalysis) + placeholderTemplate,
        },
    ];

    return [
        ...scriptPrefix,
        ...templateAnalysis.slotScopes.map(toSlotScopeEdit),
        {
            start: block.contentStart,
            end: block.contentEnd,
            replacement: chunks,
        },
    ];
}

/**
 * Builds the plain `<script>` block that registers this override's extension targets.
 *
 * Deliberately not part of `<script setup>`: that body only runs when the hidden override component
 * mounts, which is after `resolveComponentTemplates()`. A plain `<script>` runs at module eval - during
 * `loadPlugins()` - so the registry is complete while the Twig template pipeline can still act on it.
 *
 * `lang` is mirrored from the setup block because Vue requires both script blocks of an SFC to agree
 * on it.
 */
function buildNativeExtensionTargetsScript(block: ShopwareSetupBlock, templateAnalysis: TemplateAnalysis): string {
    const langAttribute = block.lang ? ` lang="${block.lang}"` : '';
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
        `<script${langAttribute}>`,
        'Shopware.Component.registerNativeExtensionTargets({',
        `    component: '${escapeSingleQuoted(block.componentName)}',`,
        ...blocksProperty,
        '});',
        '</script>\n',
    ].join('\n');
}

/**
 * @private
 */
export { buildOverrideScript };
