/**
 * @sw-package framework
 */

import { createHash } from 'node:crypto';
import path from 'node:path';
import type MagicString from 'magic-string';
import { OVERRIDE_LOCAL_STATE_KEY, RESERVED_BINDING_PREFIX } from '../naming';
import type { OverrideSetupScriptAnalysis } from '../script-analyzer';
import type { ShopwareSetupBlock } from '../sfc-parser';
import type { TemplateAnalysis } from '../template-analyzer';
import { quote } from './shared';

const NAMESPACE = `${RESERVED_BINDING_PREFIX}Namespace`;
const PREVIOUS_STATE = `${RESERVED_BINDING_PREFIX}PreviousState`;
const PROPS = `${RESERVED_BINDING_PREFIX}Props`;
const CONTEXT = `${RESERVED_BINDING_PREFIX}Context`;

/**
 * Identifies the override file across re-registrations (HMR), without leaking its path into the output.
 */
function fileKey(filename: string): string {
    const file = filename.split(/[?#]/, 1)[0];
    const relative = path.isAbsolute(file) ? path.relative(process.cwd(), file) : file;

    return createHash('sha256').update(relative.replace(/\\/g, '/')).digest('hex').slice(0, 8);
}

function buildReturn(analysis: OverrideSetupScriptAnalysis, privateBindings: string[]): string {
    if (analysis.overrideEntries.length === 0 && privateBindings.length === 0) {
        return 'return {};';
    }

    const lines = ['return {', ...analysis.overrideEntries.map((name) => `    ${name},`)];

    if (privateBindings.length > 0) {
        lines.push(
            `    ${OVERRIDE_LOCAL_STATE_KEY}: {`,
            `        [${NAMESPACE}]: {`,
            ...privateBindings.map((name) => `            ${name},`),
            '        },',
            '    },',
        );
    }

    return [...lines, '};'].join('\n');
}

/**
 * Every override local reaches the block content: public ones under their own name from the host's
 * state, the rest under the namespace. The `= {}` defaults keep hosts without override-local state
 * (Options API hosts, nested Twig blocks) from failing the destructure.
 */
function toSlotScope(publicNames: string[], privateNames: string[]): string {
    const entries = [
        ...(privateNames.length > 0
            ? [`${OVERRIDE_LOCAL_STATE_KEY}: { [${NAMESPACE}]: { ${privateNames.join(', ')} } = {} } = {}`]
            : []),
        ...publicNames,
    ];

    return entries.length > 0 ? ` #default="{ ${entries.join(', ')} }"` : '';
}

/**
 * Turns the author's `<script setup>` into a plain `<script>` that registers the override at module
 * scope, and moves the body into the registered callback. What cannot live in a function (imports,
 * `declare`, type exports) is hoisted above it.
 *
 * A `<script setup>` holding only a comment follows: without one Vue would not expose the module-scope
 * bindings (imports, the namespace symbol) to the template, and Vue drops a whitespace-only block.
 */
function lowerOverride(
    s: MagicString,
    block: ShopwareSetupBlock,
    analysis: OverrideSetupScriptAnalysis,
    template: TemplateAnalysis,
): void {
    const offset = block.contentStart;
    const bodyStart = offset + analysis.bodyStart;
    const publicNames = new Set(analysis.overrideEntries);
    const privateBindings =
        template.slotScopeInsertions.length > 0
            ? [...analysis.runtimeBindings.filter((name) => !publicNames.has(name)), ...analysis.runtimeInputAliasNames]
            : [];
    const tagStart = block.source.lastIndexOf('<script', block.contentStart);
    const closingTagEnd = block.source.indexOf('>', block.contentEnd) + 1;
    const lang = block.lang ? ` lang="${block.lang}"` : '';

    if (!block.template) {
        // The hidden registration component still mounts, and Vue warns about one without a template.
        s.prepend('<template><!-- Shopware override registration component --></template>\n');
    }

    template.slotScopeInsertions.forEach((at) => s.appendLeft(at, toSlotScope(analysis.overrideEntries, privateBindings)));
    s.overwrite(tagStart, block.contentStart, `<script${lang}>`);
    s.remove(offset + analysis.marker.start, offset + analysis.marker.end);

    analysis.hoisted
        .filter((range) => offset + range.start > bodyStart)
        .forEach((range) => {
            s.move(offset + range.start, offset + range.end, bodyStart);
            s.appendLeft(offset + range.end, '\n');
        });

    s.appendRight(
        bodyStart,
        [
            // Module scope, not the callback: the callback runs once per base instance, so a symbol
            // created there would never match the one in the template.
            ...(privateBindings.length > 0
                ? [`const ${NAMESPACE} = Symbol(${quote(`${block.componentName}.override`)});`]
                : []),
            `globalThis.Shopware.Component.__setupRuntime.v1.override(${quote(block.componentName)}, ${quote(fileKey(block.filename))}, (${PREVIOUS_STATE}, ${PROPS}, ${CONTEXT}) => {`,
            `const useSwPreviousState = () => ${PREVIOUS_STATE};`,
            `const useSwProps = () => ${PROPS};`,
            `const useSwContext = () => ${CONTEXT};`,
            '',
            '',
        ].join('\n'),
    );
    s.appendRight(block.contentEnd, `\n\n${buildReturn(analysis, privateBindings)}\n});\n`);
    s.appendLeft(closingTagEnd, `\n<script setup${lang}>/* exposes the module-scope bindings to the template */</script>`);
}

/**
 * @private
 */
export { lowerOverride };
