/**
 * @sw-package framework
 */

import { NodeTypes, type ElementNode, type TemplateChildNode } from '@vue/compiler-dom';
import type { OverrideSetupScriptAnalysis } from '../script-analyzer';
import type { ShopwareSetupBlock } from '../sfc-parser';
import { ShopwareSetupTransformError } from '../utils/transform-error';
import { collectTemplateWrites } from './template-writes';
import {
    assertOverrideTemplateTopLevel,
    assertSwBlockAttributes,
    isSwBlock,
    openingTagEnd,
    staticSwBlockAttribute,
} from './sw-block';

type TemplateAnalysis = {
    /** Right after `<sw-block` of every base block: where the generated data scope binding goes. */
    dataScopeInsertions: number[];
    /** Right before `>` of every `<sw-block extends>`: where the generated slot scope goes. */
    slotScopeInsertions: number[];
    // For the planned cross-file registry of block owners and extenders.
    ownedBlockNames: string[];
    extendedBlockNames: string[];
};

function emptyTemplateAnalysis(): TemplateAnalysis {
    return {
        dataScopeInsertions: [],
        slotScopeInsertions: [],
        ownedBlockNames: [],
        extendedBlockNames: [],
    };
}

function forEachElement(nodes: TemplateChildNode[], visit: (element: ElementNode) => void): void {
    nodes.forEach((node) => {
        if (node.type === NodeTypes.ELEMENT) {
            visit(node);
            forEachElement(node.children, visit);
        }
    });
}

function analyzeBaseTemplate(block: ShopwareSetupBlock): TemplateAnalysis {
    const analysis = emptyTemplateAnalysis();

    forEachElement(block.template?.children ?? [], (element) => {
        if (element.tag === 'sw-block') {
            assertSwBlockAttributes(element, 'base');
        }

        if (isSwBlock(element, 'name')) {
            const name = staticSwBlockAttribute(element, 'name');

            if (name !== null) {
                analysis.ownedBlockNames.push(name);
            }

            analysis.dataScopeInsertions.push(element.loc.start.offset + '<sw-block'.length);
        }
    });

    return analysis;
}

function analyzeOverrideTemplate(block: ShopwareSetupBlock, script: OverrideSetupScriptAnalysis): TemplateAnalysis {
    const analysis = emptyTemplateAnalysis();

    if (!block.template) {
        return analysis;
    }

    const overrideLocalNames = new Set([...script.runtimeBindings, ...script.runtimeInputAliasNames]);

    assertOverrideTemplateTopLevel(block.template.children);

    forEachElement(block.template.children, (element) => {
        if (element.tag === 'sw-block') {
            assertSwBlockAttributes(element, 'override');
        }

        if (!isSwBlock(element, 'extends')) {
            return;
        }

        const name = staticSwBlockAttribute(element, 'extends');

        if (name !== null) {
            analysis.extendedBlockNames.push(name);
        }

        // Override locals arrive as slot-scope locals, so a template write would only change that local.
        collectTemplateWrites(element.children).forEach((offset, writtenName) => {
            if (overrideLocalNames.has(writtenName)) {
                throw new ShopwareSetupTransformError(
                    `Cannot assign to "${writtenName}" inside <sw-block extends> content: forwarded override bindings are read-only ` +
                        'there (the write targets a slot-scope local and has no effect). Mutate the value from a method defined ' +
                        'in the override setup and call that instead.',
                    offset,
                );
            }
        });

        analysis.slotScopeInsertions.push(openingTagEnd(block.source, element));
    });

    return analysis;
}

/**
 * @private
 */
export { type TemplateAnalysis, analyzeBaseTemplate, analyzeOverrideTemplate };
