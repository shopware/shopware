/**
 * @sw-package framework
 */

/**
 * Analyzes Shopware setup templates for data-scope and override-private state wiring.
 *
 * Base templates need a data scope on each `<sw-block>`, and override templates must forward the setup
 * bindings their block content actually reads - plus rewrite every reference that reads one into the
 * generated slot scope. This module reports *where* those additions go, *which* bindings they carry,
 * and *which* ranges the rewrite replaces; the lowerers turn that into attribute and expression text,
 * so no generated syntax is decided here.
 */

import { NodeTypes, parse as parseTemplate, type TemplateChildNode } from '@vue/compiler-dom';
import type { OverrideSetupScriptAnalysis } from '../script-analyzer';
import type { ShopwareSetupBlock } from '../utils/shopware-setup-block';
import {
    type ElementNode,
    type TemplateOccurrence,
    collectTemplateReferences,
    getStaticSwBlockExtends,
    getStaticSwBlockName,
    isSwBlockExtends,
    isSwBlockName,
} from './template-references';
import {
    assertMappableForwardedReferences,
    assertOverrideTemplateTopLevel,
    assertSwBlockAttributes,
    findOpeningTagAttributeEnd,
    findOpeningTagNameEnd,
} from './sw-block-bindings';

/**
 * One reference inside `<sw-block extends>` content that lowering rewrites to its slot-scope path.
 *
 * Offsets are absolute SFC coordinates. `visibility` decides *which* path the binding is reachable
 * under - a declared override binding replaces base state and is read off the scope directly, while
 * everything else lives under this override file's namespace - and `expansion` how much surrounding
 * syntax the replacement has to reproduce. What those paths look like is the lowerer's business.
 */
type OverrideReferenceRewrite = {
    start: number;
    end: number;
    name: string;
    visibility: 'public' | 'private';
    expansion: TemplateOccurrence['expansion'];
};

/**
 * One `<sw-block extends>` whose content reads override-local bindings, and which therefore needs a
 * generated slot scope plus a rewrite of every reference into it.
 *
 * References are rewritten rather than destructured because a destructured slot prop is a plain local:
 * reads would work, but `count++` would assign to that local and never reach the real ref. Going through
 * the scope object keeps both directions live.
 */
type OverrideSlotScope = {
    at: number;
    rewrites: OverrideReferenceRewrite[];
};

/**
 * Where a template needs generated additions, plus the private setup bindings an override must return.
 *
 * Positions and binding names only - the lowerers own the attribute syntax. Non-empty `privateBindings`
 * is what tells the override lowerer to emit the namespace symbol and file those bindings under it.
 */
type TemplateAnalysis = {
    // Absolute offsets on base `<sw-block>` opening tags where the generated data scope is inserted.
    dataScopeInsertions: number[];
    slotScopes: OverrideSlotScope[];
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
        dataScopeInsertions: [],
        slotScopes: [],
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
 * Locates the slot scopes an override template needs, and the private bindings they forward.
 */
function analyzeOverrideTemplate(block: ShopwareSetupBlock, analysis: OverrideSetupScriptAnalysis): TemplateAnalysis {
    if (!block.template) {
        return emptyTemplateAnalysis();
    }

    const template = block.template;
    const templateOffset = template.contentStart;
    const ast = parseTemplate(template.content);

    // An override template may only carry <sw-block extends> blocks at its top level.
    assertOverrideTemplateTopLevel(ast.children, templateOffset);

    const slotScopes: OverrideSlotScope[] = [];
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

            const { references, occurrences, unmappableExpressions } = collectTemplateReferences(
                element.children,
                new Set(),
            );
            const forwarded = new Map<string, OverrideReferenceRewrite['visibility']>();

            analysis.runtimeBindings.forEach((binding) => {
                if (!references.has(binding.name)) {
                    return;
                }

                // Public override bindings replace base state and are read off the scope under their own
                // name; only private ones need the deterministic override namespace.
                if (overrideLocalNames.has(binding.name)) {
                    forwarded.set(binding.name, 'public');
                    return;
                }

                forwarded.set(binding.name, 'private');
                privateBindings.add(binding.name);
            });

            // Runtime input aliases (useSwPreviousState/useSwProps/useSwContext) are never public
            // override bindings, but the override template can still reference them, so forward them
            // through the private namespace like any other referenced setup local.
            analysis.runtimeInputAliasNames.forEach((name) => {
                if (!references.has(name) || forwarded.has(name)) {
                    return;
                }

                forwarded.set(name, 'private');
                privateBindings.add(name);
            });

            // Every forwarded reference has to be rewritten, so one that cannot be addressed is a hard
            // stop rather than a silently skipped edit.
            assertMappableForwardedReferences(unmappableExpressions, new Set(forwarded.keys()), templateOffset);

            const rewrites = occurrences.flatMap<OverrideReferenceRewrite>((occurrence) => {
                const visibility = forwarded.get(occurrence.name);

                if (!visibility) {
                    return [];
                }

                return [
                    {
                        start: templateOffset + occurrence.start,
                        end: templateOffset + occurrence.end,
                        name: occurrence.name,
                        visibility,
                        expansion: occurrence.expansion,
                    },
                ];
            });

            if (rewrites.length > 0) {
                slotScopes.push({
                    at: template.contentStart + findOpeningTagAttributeEnd(template.content, element.loc.start.offset),
                    rewrites,
                });
            }
        }
    });

    return {
        dataScopeInsertions: [],
        slotScopes,
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
    const dataScopeInsertions: number[] = [];
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

            dataScopeInsertions.push(
                template.contentStart + findOpeningTagNameEnd(template.content, element.loc.start.offset),
            );
        }
    });

    return {
        dataScopeInsertions,
        slotScopes: [],
        privateBindings: new Set<string>(),
        ownedBlockNames,
        extendedBlockNames: [],
    };
}

/**
 * @private
 */
export {
    type OverrideReferenceRewrite,
    type OverrideSlotScope,
    type TemplateAnalysis,
    analyzeBaseTemplate,
    analyzeOverrideTemplate,
    emptyTemplateAnalysis,
};
