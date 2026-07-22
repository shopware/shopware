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

import crypto from 'crypto';
import path from 'path';
import { NodeTypes, parse as parseTemplate } from '@vue/compiler-dom';
import type { TemplateChildNode } from '@vue/compiler-core';
import type { ShopwareSetupScriptAnalysis } from '../script-analyzer';
import type { ShopwareSetupBlock } from '../utils/shopware-setup-block';
import {
    type SlotMapping,
    type TemplateEdit,
    collectTemplateReferences,
    isSwBlockExtends,
    isSwBlockName,
} from './template-references';
import {
    assertNoAuthoredSwBlockBindings,
    createGeneratedSlotEdit,
    createPrivateSlotMapping,
    findOpeningTagNameEnd,
} from './sw-block-bindings';

/**
 * Carries template source edits plus the private setup bindings that must be returned by an override.
 *
 * `privateNamespace` is deterministic per override file so several override SFCs can pass local
 * fields through the same reserved slot-scope key without colliding.
 */
type TemplateAnalysis = {
    edits: TemplateEdit[];
    privateBindings: Set<string>;
    privateNamespace: string | null;
};

/**
 * Keeps override-private namespace names stable for builds, tests, and debugging.
 *
 */
function createOverridePrivateNamespace(filename: string, componentName: string): string {
    const normalizedFilename = path.normalize(filename).split(path.sep).join('/');
    // sha1 (Node builtin) is used for a stable, well-spread suffix only - this is not security hashing.
    const hash = crypto.createHash('sha1').update(`${normalizedFilename}:${componentName}`).digest('hex').slice(0, 5);
    const readableFilename = path
        .basename(normalizedFilename)
        .replace(/\.[^.]+$/u, '')
        .replace(/[^A-Za-z0-9_$]/gu, '_')
        .replace(/_+/gu, '_')
        .replace(/^([^A-Za-z_$])/u, '_$1');

    return `${readableFilename}_${hash}`;
}

/**
 * Creates the template edits and private return bindings required by override SFCs.
 *
 */
function analyzeOverrideTemplate(block: ShopwareSetupBlock, analysis: ShopwareSetupScriptAnalysis): TemplateAnalysis {
    if (!block.template) {
        return {
            edits: [],
            privateBindings: new Set<string>(),
            privateNamespace: null,
        };
    }

    const ast = parseTemplate(block.template.content);
    const edits: TemplateEdit[] = [];
    const privateBindings = new Set<string>();
    const overrideLocalNames = new Set<string>(analysis.overrideEntries);
    const privateNamespace = createOverridePrivateNamespace(block.filename, block.componentName);

    function visit(node: TemplateChildNode): void {
        if (node.type === NodeTypes.ELEMENT && node.tag === 'sw-block') {
            assertNoAuthoredSwBlockBindings(node as Parameters<typeof assertNoAuthoredSwBlockBindings>[0]);
        }

        if (isSwBlockExtends(node)) {
            const references = collectTemplateReferences(node.children, new Set());
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
                ...(privateLocalNames.length > 0 ? [createPrivateSlotMapping(privateNamespace, privateLocalNames)] : []),
                ...publicMappings,
            ];

            if (mappings.length > 0) {
                edits.push(createGeneratedSlotEdit(block, node, mappings));
            }
        }

        if (node.type === NodeTypes.ELEMENT) {
            node.children.forEach(visit);
        }
    }

    ast.children.forEach(visit);

    return {
        edits,
        privateBindings,
        privateNamespace,
    };
}

/**
 * Creates template edits required by base SFCs.
 *
 */
function analyzeBaseTemplate(block: ShopwareSetupBlock): TemplateAnalysis {
    if (!block.template) {
        return {
            edits: [],
            privateBindings: new Set<string>(),
            privateNamespace: null,
        };
    }

    const ast = parseTemplate(block.template.content);
    const edits: TemplateEdit[] = [];

    function visit(node: TemplateChildNode): void {
        if (node.type === NodeTypes.ELEMENT && node.tag === 'sw-block') {
            assertNoAuthoredSwBlockBindings(node as Parameters<typeof assertNoAuthoredSwBlockBindings>[0]);
        }

        if (isSwBlockName(node)) {
            if (!block.template) {
                return;
            }

            const insertionPoint = findOpeningTagNameEnd(block.template.content, node.loc.start.offset);

            edits.push({
                start: block.template.contentStart + insertionPoint,
                end: block.template.contentStart + insertionPoint,
                replacement: ' :data="$dataScope"',
            });
        }

        if (node.type === NodeTypes.ELEMENT) {
            node.children.forEach(visit);
        }
    }

    ast.children.forEach(visit);

    return {
        edits,
        privateBindings: new Set<string>(),
        privateNamespace: null,
    };
}

export { type TemplateAnalysis, analyzeBaseTemplate, analyzeOverrideTemplate, createOverridePrivateNamespace };
