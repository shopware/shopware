/**
 * @sw-package framework
 */

/**
 * Lowers base Shopware setup scripts by keeping the author's body native and
 * appending a generated `Shopware.Component.attachOverrides(...)` footer.
 *
 * The author's code runs as plain `<script setup>` - all Vue macros stay in place, nothing is
 * hoisted, nothing is wrapped. Every top-level runtime binding is renamed to a reserved
 * `__swSetupAuthor_<name>` alias, and the footer re-declares the original names by destructuring the
 * override wrapper, so templates read overrideable state exactly like before while the body text
 * itself never moves.
 */

import { generated, trim, type SourceChunk } from '../source-edits/chunks';
import { transformRanges } from '../source-edits/transform-ranges';
import type { ShopwareSetupScriptAnalysis } from '../script-analyzer';
import type { ShopwareSetupBlock } from '../utils/shopware-setup-block';
import { escapeSingleQuoted, formatObjectProperties } from './shared';

/**
 * Formats the public/private maps passed into the override wrapper, mapping each original name to
 * its renamed author binding.
 */
function formatStateMap(names: string[], spaces: number): string {
    return formatObjectProperties(
        names.map((name) => `${name}: __swSetupAuthor_${name}`),
        spaces,
    );
}

/**
 * Lowers base mode into a native body plus the generated override-functionality footer.
 */
function buildBaseScript(block: ShopwareSetupBlock, analysis: ShopwareSetupScriptAnalysis): SourceChunk[] {
    const publicLocalNames = new Set(analysis.publicEntries);
    const privateNames = analysis.runtimeBindings
        .filter((binding) => !publicLocalNames.has(binding.name))
        .map((binding) => binding.name);
    const destructureEntries = [
        ...analysis.runtimeBindings.map((binding) => binding.name),
        '__swOverride',
    ];

    const body = transformRanges(block, analysis.markerRemovals, analysis.renameEdits);

    // attachOverrides() reads props from the current instance, so the footer never threads a props
    // binding through — which also lets destructured defineProps() work (there is no props binding).
    const footer = [
        'const {',
        ...destructureEntries.map((entry) => `    ${entry},`),
        '} = Shopware.Component.attachOverrides({',
        `    name: '${escapeSingleQuoted(block.componentName)}',`,
        `    public: ${formatStateMap(analysis.publicEntries, 8)},`,
        `    private: ${formatStateMap(privateNames, 8)},`,
        '});',
    ].join('\n');

    return [
        generated(`${block.openingTagSource}\n`),
        // useSwContext() must exist before the author's body runs, so it is a header, not a footer.
        generated('const useSwContext = () => Shopware.Component.getComponentContext();\n\n'),
        trim([body].flat()),
        generated(`\n\n${footer}\n</script>`),
    ];
}

export { buildBaseScript };
