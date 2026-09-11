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
 * itself never moves. The footer closes with the generated `defineExpose()` that gives the props and
 * the public bindings to a parent holding a template ref.
 */

import { buildLegacyAssets } from './legacy-assets';
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
    const original = toAuthorAlias(target.localName);
    const alias = target.write
        ? `__swSetupDispatch.binding('${escapeSingleQuoted(target.localName)}', () => ${original}, value => { ${original} = value; }).value`
        : target.dispatch
          ? `(__swSetupDispatch.read('${escapeSingleQuoted(target.localName)}', () => ${original}))`
          : original;

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
function toDataScopeEdit({ at, locals }: TemplateAnalysis['dataScopeInsertions'][number]): SourceEdit {
    return {
        start: at,
        end: at,
        replacement: locals.length
            ? ` :data="__swSetupBlockScope($dataScope, { ${locals.join(', ')} })"`
            : ' :data="$dataScope"',
    };
}

function toSlotDefinitionEdit(receiver: TemplateAnalysis['slotDefinitionReceivers'][number]): SourceEdit {
    const scope = receiver.locals.length
        ? `__swSetupBlockScope($dataScope, { ${receiver.locals.join(', ')} })`
        : '$dataScope';
    const groups = receiver.groups.map(
        (group) =>
            `{ name: ${JSON.stringify(group.name)}, names: [${group.names.join(', ')}], children: ${JSON.stringify(group.children)} }`,
    );
    const expression = `{ scope: ${scope}, groups: [${groups.join(', ')}] }`;
    const attribute = expression.replace(/&/g, '&amp;').replace(/"/g, '&quot;');
    return { start: receiver.at, end: receiver.at, replacement: ` :__swSlotBlocks="${attribute}"` };
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
    const assets = buildLegacyAssets(block, analysis);
    const legacyOptions = [
        ...(Object.keys(analysis.legacyOptions.members).length
            ? [
                  `legacyOptionsMembers: ${JSON.stringify(analysis.legacyOptions.members)}`,
              ]
            : []),
        ...(Object.keys(analysis.legacyOptions.bindings).length
            ? [
                  `legacyOptionsBindings: ${JSON.stringify(analysis.legacyOptions.bindings)}`,
              ]
            : []),
    ]
        .map((option) => `${option}, `)
        .join('');
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
    const body = transformRanges(block, analysis.markerStatements, [
        ...analysis.renameTargets.map((target) => ({ ...target, replacement: toRenameReplacement(target) })),
        ...(analysis.optionsArgument
            ? [
                  {
                      start: analysis.optionsArgument.start,
                      end: analysis.optionsArgument.start,
                      replacement: `({ ${legacyOptions}__swExtendable: true, name: '${escapeSingleQuoted(block.componentName)}', ...(`,
                  },
                  { start: analysis.optionsArgument.end, end: analysis.optionsArgument.end, replacement: ') })' },
              ]
            : []),
    ]);

    // attachOverrides() reads props from the current instance, so the footer never threads a props
    // binding through — which also lets destructured defineProps() work (there is no props binding).
    const footer = [
        ...(!analysis.optionsArgument
            ? [
                  `defineOptions({ ${legacyOptions}name: '${escapeSingleQuoted(block.componentName)}', __swExtendable: true });`,
              ]
            : []),
        'const {',
        ...destructureEntries.map((entry) => `    ${entry},`),
        '} = Shopware.Component.attachOverrides({',
        `    name: '${escapeSingleQuoted(block.componentName)}',`,
        `    public: ${formatStateMap(analysis.publicEntries, 8)},`,
        `    private: ${formatStateMap(privateNames, 8)},`,
        '});',
        `__swSetupDispatch.attach({ ${destructureEntries.join(', ')} });`,
        ...assets.declarations,
        '',
        // swDefinePublic() is the parent-facing surface too, so the call is generated here and authoring
        // one is rejected. Props join it because exposing anything closes a component to everything
        // else, and reading a prop off a ref must keep working. After the destructure, which hands out
        // the override-aware customRefs - that is what makes a parent's `treeItem.opened = false` reach
        // the component's own state.
        `defineExpose(${formatObjectProperties(
            [
                '...Shopware.Component.getExposedProps()',
                ...analysis.publicEntries,
            ],
            4,
        )});`,
    ].join('\n');

    return [
        ...assets.edits,
        ...templateAnalysis.slotDefinitionRemovals.map((range) => ({ ...range, replacement: '' })),
        ...templateAnalysis.slotDefinitionReceivers.map(toSlotDefinitionEdit),
        ...templateAnalysis.dataScopeInsertions.map(toDataScopeEdit),
        {
            start: block.contentStart,
            end: block.contentEnd,
            replacement: [
                ...(templateAnalysis.dataScopeInsertions.some((entry) => entry.locals.length) ||
                templateAnalysis.slotDefinitionReceivers.length
                    ? [
                          generated(
                              '\nconst __swSetupBlockScope = Shopware.Component.createBlockDataScope;\nconst __swSetupSlotNames = Shopware.Component.mapSlotNames;\n',
                          ),
                      ]
                    : []),
                generated('\nconst __swSetupDispatch = Shopware.Component.createSetupDispatch();\n'),
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
