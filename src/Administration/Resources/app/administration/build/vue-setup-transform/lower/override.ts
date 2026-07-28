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
 */

import { fromSource, generated, indent, type SourceChunk } from '../source-edits/chunks';
import type { OverrideSetupScriptAnalysis } from '../script-analyzer';
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
 * Lowers override mode into a hidden override component consumed by
 * registerOverrideComponent.
 */
function buildOverrideScript(
    block: ShopwareSetupBlock,
    analysis: OverrideSetupScriptAnalysis,
    overridePrivateBindings: Set<string>,
): SourceChunk[] {
    // Generated bindings use the reserved `__swSetup` prefix (rejected as user bindings), so they are
    // deterministic and never collide.
    const previousStateName = '__swSetupPreviousState';
    const propsName = '__swSetupProps';
    const contextName = '__swSetupContext';
    // Only the marker statements are removed; the override helpers are emitted as generated headers above.
    const callbackBody = transformRanges(block, analysis.bodyRemovals, []);
    const body = [
        generated(`const useSwPreviousState = () => ${previousStateName};\n`),
        generated(`const useSwProps = () => ${propsName};\n`),
        generated(`const useSwContext = () => ${contextName};\n\n`),
        ...callbackBody,
        generated(`\n\n${buildOverrideReturn(analysis, overridePrivateBindings)}`),
    ];
    const chunks: SourceChunk[] = [generated(`${block.openingTagSource}\n`)];

    analysis.imports.forEach((importBlock) => {
        chunks.push(fromSource(block, importBlock));
        chunks.push(generated('\n'));
    });

    if (analysis.imports.length > 0) {
        chunks.push(generated('\n'));
    }

    // Only needed when this override actually forwards private locals into a <sw-block extends> scope;
    // an override that only replaces public bindings has nothing to file under the namespace.
    //
    // Declared at module root, NOT inside the callback: the callback runs once per base-component
    // instance, so a symbol created there would be a different value every time and the state lookup
    // would never match. Module scope evaluates once, giving one stable symbol per override file - and it
    // stays template-visible, so the generated computed key resolves.
    if (overridePrivateBindings.size > 0) {
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
        indent(body, 4),
        generated('\n});\n</script>'),
    );

    return chunks;
}

/**
 * @private
 */
export { buildOverrideScript };
