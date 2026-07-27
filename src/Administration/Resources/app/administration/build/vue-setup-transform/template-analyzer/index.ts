/**
 * @sw-package framework
 */

/**
 * Analyzes Shopware setup templates for data-scope and override-private state wiring.
 *
 * Base templates receive missing `sw-block` data scopes, while override templates expose only the
 * setup bindings their override slots actually read. The resulting edits are applied with the script
 * transform so the Vue compiler sees a normal SFC.
 */

import { NodeTypes, parse as parseTemplate, type TemplateChildNode } from '@vue/compiler-dom';
import type { ShopwareSetupScriptAnalysis } from '../script-analyzer';
import type { ShopwareSetupBlock } from '../utils/shopware-setup-block';
import {
    type ElementNode,
    type SlotMapping,
    type TemplateEdit,
    collectTemplateReferences,
    getStaticSwBlockExtends,
    getStaticSwBlockName,
    isSwBlockExtends,
    isSwBlockName,
} from './template-references';
import {
    assertNoWritesToForwardedBindings,
    assertOverrideTemplateTopLevel,
    assertSwBlockAttributes,
    createGeneratedSlotEdit,
    createPrivateSlotMapping,
    findOpeningTagNameEnd,
} from './sw-block-bindings';

/**
 * Carries template source edits plus the private setup bindings that must be returned by an override.
 *
 * Non-empty `privateBindings` is what tells the lowerer to emit the namespace symbol and file those
 * bindings under it; the binding name itself is a fixed constant, so it is not carried here.
 */
type TemplateAnalysis = {
    edits: TemplateEdit[];
    privateBindings: Set<string>;
    // Static names of the base `<sw-block name="...">` blocks this component owns. Emitted so a later
    // branch can build a block-ownership registry (block name <-> owning component) and reject, at
    // compile time, overrides that extend a block whose owner cannot provide the override-local scope.
    ownedBlockNames: string[];
    // Static names of the blocks an override `<sw-block extends="...">` extends. The registry's other
    // half: a later branch cross-checks these against the emitted ownership to fail loudly on a typo'd
    // or non-existent block name (per-file analysis can't see other files, so this is done at build time).
    extendedBlockNames: string[];
};

/**
 * The analysis result for a template that contributes nothing - no template block, or no matched elements.
 *
 * @private
 */
function emptyTemplateAnalysis(): TemplateAnalysis {
    return {
        edits: [],
        privateBindings: new Set<string>(),
        ownedBlockNames: [],
        extendedBlockNames: [],
    };
}

/**
 * Visits every element node in a template subtree, pre-order, recursing into children.
 *
 * Both analyzers share this walk; each supplies only its per-element work (the sw-block guard plus its
 * mode-specific data-scope / forwarding handling).
 */
function forEachTemplateElement(nodes: TemplateChildNode[], visit: (element: ElementNode) => void): void {
    nodes.forEach((node) => {
        if (node.type !== NodeTypes.ELEMENT) {
            return;
        }

        const element = node as ElementNode;
        visit(element);
        forEachTemplateElement(element.children, visit);
    });
}

/**
 * Creates the template edits and private return bindings required by override SFCs.
 *
 */
function analyzeOverrideTemplate(block: ShopwareSetupBlock, analysis: ShopwareSetupScriptAnalysis): TemplateAnalysis {
    if (!block.template) {
        return emptyTemplateAnalysis();
    }

    const templateOffset = block.template.contentStart;
    const ast = parseTemplate(block.template.content);

    // An override template may only carry <sw-block extends> blocks at its top level.
    assertOverrideTemplateTopLevel(ast.children, templateOffset);

    const edits: TemplateEdit[] = [];
    const privateBindings = new Set<string>();
    const extendedBlockNames: string[] = [];
    const overrideLocalNames = new Set<string>(analysis.overrideEntries);

    forEachTemplateElement(ast.children, (element) => {
        if (element.tag === 'sw-block') {
            assertSwBlockAttributes(element, 'override', templateOffset);
        }

        if (isSwBlockExtends(element)) {
            const extendedName = getStaticSwBlockExtends(element);

            if (extendedName !== null) {
                extendedBlockNames.push(extendedName);
            }

            const { references, writeTargets } = collectTemplateReferences(element.children, new Set());

            // Forwarded bindings are read-only in the slot; reject template writes to them.
            assertNoWritesToForwardedBindings(
                writeTargets,
                new Set([
                    ...analysis.runtimeBindingNames,
                    ...analysis.runtimeInputAliasNames,
                ]),
                templateOffset,
            );

            const publicMappings: SlotMapping[] = [];
            const privateLocalNames: string[] = [];

            analysis.runtimeBindings.forEach((binding) => {
                if (!references.has(binding.name)) {
                    return;
                }

                // Public override bindings keep their own name in the slot scope; only private
                // ones need the deterministic override namespace.
                if (overrideLocalNames.has(binding.name)) {
                    publicMappings.push({
                        sourceKey: binding.name,
                        source: binding.name,
                    });
                    return;
                }

                privateBindings.add(binding.name);
                privateLocalNames.push(binding.name);
            });

            // Runtime input aliases (useSwPreviousState/useSwProps/useSwContext) are never public
            // override bindings, but the override template can still reference them, so forward them
            // through the private namespace like any other referenced setup local.
            analysis.runtimeInputAliasNames.forEach((name) => {
                if (!references.has(name) || privateBindings.has(name)) {
                    return;
                }

                privateBindings.add(name);
                privateLocalNames.push(name);
            });

            const mappings = [
                ...(privateLocalNames.length > 0 ? [createPrivateSlotMapping(privateLocalNames)] : []),
                ...publicMappings,
            ];

            if (mappings.length > 0) {
                edits.push(createGeneratedSlotEdit(block, element, mappings));
            }
        }
    });

    return {
        edits,
        privateBindings,
        ownedBlockNames: [],
        extendedBlockNames,
    };
}

/**
 * Creates template edits required by base SFCs.
 *
 */
function analyzeBaseTemplate(block: ShopwareSetupBlock): TemplateAnalysis {
    if (!block.template) {
        return emptyTemplateAnalysis();
    }

    const template = block.template;
    const ast = parseTemplate(template.content);
    const edits: TemplateEdit[] = [];
    const ownedBlockNames: string[] = [];

    forEachTemplateElement(ast.children, (element) => {
        if (element.tag === 'sw-block') {
            assertSwBlockAttributes(element, 'base', template.contentStart);
        }

        if (isSwBlockName(element)) {
            const blockName = getStaticSwBlockName(element);

            if (blockName !== null) {
                ownedBlockNames.push(blockName);
            }

            const insertionPoint = findOpeningTagNameEnd(template.content, element.loc.start.offset);

            edits.push({
                start: template.contentStart + insertionPoint,
                end: template.contentStart + insertionPoint,
                replacement: ' :data="$dataScope"',
            });
        }
    });

    return {
        edits,
        privateBindings: new Set<string>(),
        ownedBlockNames,
        extendedBlockNames: [],
    };
}

/**
 * @private
 */
export { type TemplateAnalysis, analyzeBaseTemplate, analyzeOverrideTemplate, emptyTemplateAnalysis };
