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
import { OVERRIDE_NAMESPACE_BINDING, OVERRIDE_SCOPE_BINDING, RESERVED_OVERRIDE_STATE_NAME } from '../script-analyzer/macros';
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
 * `__swOverride` channel, keyed by the module's namespace symbol.
 */
function toReferencePath(rewrite: OverrideReferenceRewrite): string {
    if (rewrite.visibility === 'public') {
        return `${OVERRIDE_SCOPE_BINDING}.${rewrite.name}`;
    }

    return `${OVERRIDE_SCOPE_BINDING}.${RESERVED_OVERRIDE_STATE_NAME}[${OVERRIDE_NAMESPACE_BINDING}].${rewrite.name}`;
}

/**
 * The edit that replaces one reference with its slot-scope path.
 *
 * A plain occurrence is swapped for the path. A shorthand object property (`{ info }`) shares its range
 * with the property key, so the key is written out. Vue's same-name binding shorthand (`:info`) has no
 * value at all and the occurrence is the empty span behind it, so the edit is the value it was standing
 * in for.
 */
function toReferenceRewriteEdit(rewrite: OverrideReferenceRewrite): SourceEdit {
    const path = toReferencePath(rewrite);
    const replacement = (() => {
        if (rewrite.expansion === 'shorthand-property') {
            return `${rewrite.name}: ${path}`;
        }

        return rewrite.expansion === 'same-name-shorthand' ? `="${path}"` : path;
    })();

    return {
        start: rewrite.start,
        end: rewrite.end,
        replacement,
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

    const registrationTemplate: SourceEdit[] = block.template
        ? []
        : [
              {
                  start: 0,
                  end: 0,
                  replacement: '<template><!-- Shopware override registration component --></template>\n',
              },
          ];

    return [
        ...registrationTemplate,
        ...templateAnalysis.slotScopes.flatMap(toSlotScopeEdits),
        {
            start: block.contentStart,
            end: block.contentEnd,
            replacement: chunks,
        },
    ];
}

/**
 * @private
 */
export { buildOverrideScript };
