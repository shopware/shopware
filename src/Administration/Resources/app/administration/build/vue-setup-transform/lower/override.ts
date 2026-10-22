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
import type { OverrideReferenceRewrite, OverrideSlotScope, TemplateAnalysis } from '../template-analyzer';
import type { ShopwareSetupBlock } from '../utils/shopware-setup-block';
import { escapeSingleQuoted } from './shared';
import {
    OVERRIDE_NAMESPACE_BINDING,
    OVERRIDE_SCOPE_ACCESSOR,
    OVERRIDE_SCOPE_BINDING,
    RESERVED_OVERRIDE_STATE_NAME,
} from '../script-analyzer/macros';
import { transformRanges } from '../source-edits/transform-ranges';

/**
 * Builds the override callback payload from declared replacements and template-used private aliases.
 */
function buildOverrideReturn(analysis: OverrideSetupScriptAnalysis, overridePrivateBindings: Set<string>): string {
    const privateBindings = Array.from(overridePrivateBindings);

    if (analysis.overrideEntries.length === 0 && privateBindings.length === 0) {
        return 'return {};';
    }

    const lines = ['return {', ...analysis.overrideEntries.map((property) => `    ${property},`)];

    if (privateBindings.length > 0) {
        lines.push(
            `    ${RESERVED_OVERRIDE_STATE_NAME}: {`,
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
 * The path a forwarded binding is read through inside `<sw-block extends>` content.
 *
 * The slot scope is the base component's data scope, so a declared override binding - which replaces
 * base state - is a property of it; everything else this override forwards sits under the reserved
 * `__swOverride` channel, keyed by the module's namespace symbol. That lookup goes through the generated
 * accessor rather than a computed member: a reference may sit inside a dynamic directive argument
 * (`@[eventName]`), which Vue ends at the first `]`.
 */
function toReferencePath(rewrite: OverrideReferenceRewrite): string {
    if (rewrite.visibility === 'public') {
        return `${OVERRIDE_SCOPE_BINDING}.${rewrite.name}`;
    }

    return `${OVERRIDE_SCOPE_ACCESSOR}(${OVERRIDE_SCOPE_BINDING}).${rewrite.name}`;
}

/**
 * The text that takes one reference's place, given its slot-scope path.
 *
 * A plain occurrence is swapped for the path. A shorthand object property (`{ info }`) shares its range
 * with the property key, so the key is written out. Vue's same-name binding shorthand (`:info`) has no
 * value at all and the occurrence is the empty span behind it, so the edit is the value it was standing
 * in for.
 */
function toReplacement(rewrite: OverrideReferenceRewrite, path: string): string {
    switch (rewrite.expansion) {
        case 'shorthand-property':
            return `${rewrite.name}: ${path}`;
        case 'same-name-shorthand':
            return `="${path}"`;
        case 'plain':
            return path;
    }
}

/**
 * The edit that replaces one reference with its slot-scope path.
 */
function toReferenceRewriteEdit(rewrite: OverrideReferenceRewrite): SourceEdit {
    return {
        start: rewrite.start,
        end: rewrite.end,
        replacement: toReplacement(rewrite, toReferencePath(rewrite)),
    };
}

/**
 * The generated `#default` slot scope that carries the base component's data scope into `<sw-block
 * extends>` content, plus the rewrite of every reference that reads through it.
 *
 * The whole scope is bound under one name instead of destructured: a destructured slot prop is a plain
 * local, so `{{ count }}` would read correctly but `count++` would assign to that local and never reach
 * the ref behind it. Authoring `#default` on `<sw-block>` is rejected, so there is never a user pattern
 * to merge with.
 */
function toSlotScopeEdits(scope: OverrideSlotScope): SourceEdit[] {
    return [
        {
            start: scope.at,
            end: scope.at,
            replacement: ` #default="${OVERRIDE_SCOPE_BINDING}"`,
        },
        ...scope.rewrites.map(toReferenceRewriteEdit),
    ];
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
 * Emits the script content, one slot scope plus its reference rewrites per `<sw-block extends>` that
 * forwards bindings, and a generated `<template>` when the override has none - the hidden component
 * only registers its callback once it mounts, and Vue warns about a component with neither template
 * nor render function.
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
    // stays template-visible, so the generated accessor resolves.
    if (templateAnalysis.privateBindings.size > 0) {
        chunks.push(
            generated(
                `const ${OVERRIDE_NAMESPACE_BINDING} = Symbol('${escapeSingleQuoted(block.componentName)}.override');\n` +
                    `const ${OVERRIDE_SCOPE_ACCESSOR} = (scope) => scope.${RESERVED_OVERRIDE_STATE_NAME}[${OVERRIDE_NAMESPACE_BINDING}];\n\n`,
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

    // Only emitted when the override brings no template of its own; the extension-targets registration
    // is emitted either way, so it cannot ride along on this branch.
    const placeholderTemplate = block.template
        ? ''
        : '<template><!-- Shopware override registration component --></template>\n';

    return [
        ...buildNativeExtensionTargetsEdits(block, templateAnalysis, placeholderTemplate),
        ...templateAnalysis.componentNameInsertions.map((at) => toComponentNameEdit(at, block.componentName)),
        ...templateAnalysis.slotScopes.flatMap(toSlotScopeEdits),
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
