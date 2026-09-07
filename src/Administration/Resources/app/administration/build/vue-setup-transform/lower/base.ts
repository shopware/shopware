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

import { generated } from '../source-edits/chunks';
import type { SourceEdit } from '../source-edits/apply-source-edits';
import { transformRanges } from '../source-edits/transform-ranges';
import type { BaseSetupScriptAnalysis } from '../script-analyzer';
import type { TemplateAnalysis } from '../template-analyzer';
import { SHOPWARE_SETUP_INTERNAL_PREFIX } from '../script-analyzer/macros';
import type { ShopwareSetupBlock } from '../utils/shopware-setup-block';
import { escapeSingleQuoted, formatObjectProperties } from './shared';

/**
 * The one place the base alias scheme is spelled out: an author binding `count` becomes
 * `__swSetupAuthor_count`, which the footer then re-declares under the original name.
 *
 * It builds on the reserved `__swSetup` prefix that `validation.ts` rejects for author bindings, which
 * is what makes an alias collision impossible.
 */
function toAuthorAlias(localName: string): string {
    return `${SHOPWARE_SETUP_INTERNAL_PREFIX}Author_${localName}`;
}

/**
 * Renders one rename occurrence, reproducing the syntax the analyzer flagged.
 *
 * `count` -> `__swSetupAuthor_count`, `{ count }` -> `{ count: __swSetupAuthor_count }`,
 * `export type { C }` -> `export type { __swSetupAuthor_C as C }`. The two expanded forms exist because
 * the name that must survive shares its source range with the occurrence being replaced.
 */
function toRenameReplacement(target: BaseSetupScriptAnalysis['renameTargets'][number]): string {
    const alias = toAuthorAlias(target.localName);

    if (target.expansion === 'shorthand-property') {
        return `${target.localName}: ${alias}`;
    }

    return target.expansion === 'shorthand-export' ? `${alias} as ${target.localName}` : alias;
}

/**
 * Formats the public/private maps passed into the override wrapper, mapping each original name to
 * its renamed author binding.
 */
function formatStateMap(names: string[], spaces: number): string {
    return formatObjectProperties(
        names.map((name) => `${name}: ${toAuthorAlias(name)}`),
        spaces,
    );
}

/**
 * The generated attribute through which a base `<sw-block>` reads the data scope its overrides write.
 *
 * `$dataScope` resolves against the scope `attachOverrides()` registers for the instance; authoring the
 * attribute is rejected, so the transform owns the whole binding.
 */
function toDataScopeEdit(at: number): SourceEdit {
    return {
        start: at,
        end: at,
        replacement: ' :data="$dataScope"',
    };
}

/**
 * Lowers base mode into a native body plus the generated override-functionality footer.
 *
 * Edits the script content only - the author's `<script setup>` tags are left alone - plus one data-scope
 * attribute per `<sw-block>` the template analysis located.
 */
function buildBaseScript(
    block: ShopwareSetupBlock,
    analysis: BaseSetupScriptAnalysis,
    templateAnalysis: TemplateAnalysis,
): SourceEdit[] {
    const publicLocalNames = new Set(analysis.publicEntries);
    const privateNames = analysis.runtimeBindings
        .filter((binding) => !publicLocalNames.has(binding.name))
        .map((binding) => binding.name);
    // Only the author's own runtime bindings are re-declared. Override-local `__swOverride` is not
    // destructured here: a base component reaches its block data scope through the scope
    // `attachOverrides` registers (getScriptSetupDataScope), never through a setup-return binding.
    const destructureEntries = analysis.runtimeBindings.map((binding) => binding.name);

    // Base mode drops the compile-time markers and rewrites every author binding to its alias; the body
    // itself stays exactly where it was written.
    const body = transformRanges(
        block,
        analysis.markerStatements,
        analysis.renameTargets.map((target) => ({ ...target, replacement: toRenameReplacement(target) })),
    );

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
        ...templateAnalysis.dataScopeInsertions.map(toDataScopeEdit),
        {
            start: block.contentStart,
            end: block.contentEnd,
            replacement: [
                ...body,
                generated(`\n\n${footer}\n`),
            ],
        },
    ];
}

/**
 * @private
 */
export { buildBaseScript };
