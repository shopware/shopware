/** @sw-package framework */
import { isBuiltInDirective } from '@vue/shared';
import { parse, NodeTypes, ElementTypes, type TemplateChildNode } from '@vue/compiler-dom';
import type { BaseSetupScriptAnalysis } from '../script-analyzer';
import type { ShopwareSetupBlock } from '../utils/shopware-setup-block';
import type { SourceEdit } from '../source-edits/apply-source-edits';
import { collectBindingPatternNames, getForDirective, type DirectiveNode } from '../template-analyzer/template-references';

const normalize = (name: string) => name.replace(/-/g, '').toLowerCase();

/**
 * Keep template-used lexical assets available to runtime-compiled Twig. Both templates resolve
 * through the same binding, so a legacy local registration also replaces the base SFC asset.
 * @private
 */
export function buildLegacyAssets(
    block: ShopwareSetupBlock,
    analysis: BaseSetupScriptAnalysis,
): {
    edits: SourceEdit[];
    declarations: string[];
} {
    const edits: SourceEdit[] = [];
    const declarations: string[] = [];
    if (!block.template) return { edits, declarations };
    const template = block.template;
    const bindings = [
        ...analysis.runtimeBindingNames,
        ...analysis.importedBindings,
        ...(block.moduleBindings ?? []),
    ];
    const generated = new Map<string, string>();
    function asset(kind: 'components' | 'directives', name: string, binding: string): string {
        const key = `${kind}:${name}`;
        const existing = generated.get(key);
        if (existing) return existing;
        let identifier = kind === 'components' ? `SwSetupComponent${generated.size}` : `vSwSetupAsset${generated.size}`;
        while (bindings.includes(identifier)) identifier += '_';
        generated.set(key, identifier);
        const fallback = analysis.runtimeBindingNames.has(binding) ? `__swSetupAuthor_${binding}` : binding;
        declarations.push(
            `const ${identifier} = Shopware.Component.resolveLegacyAsset(${JSON.stringify(kind)}, ${JSON.stringify(name)}, ${JSON.stringify(binding)}, () => ${fallback});`,
        );
        return identifier;
    }
    function replace(start: number, length: number, replacement: string): void {
        edits.push({ start: template.contentStart + start, end: template.contentStart + start + length, replacement });
    }
    function visit(nodes: TemplateChildNode[], inherited: Set<string>): void {
        for (const node of nodes) {
            if (node.type !== NodeTypes.ELEMENT) continue;
            const locals = new Set(inherited);
            collectBindingPatternNames(getForDirective(node)).forEach((name) => locals.add(name));
            if (node.tagType === ElementTypes.COMPONENT && node.tag !== 'sw-block' && node.tag !== 'component') {
                const binding = bindings.find((name) => normalize(name) === normalize(node.tag));
                if (binding && !locals.has(binding)) {
                    const identifier = asset('components', node.tag, binding);
                    replace(node.loc.start.offset + 1, node.tag.length, identifier);
                    if (!node.isSelfClosing) {
                        const closing = template.content.lastIndexOf('</', node.loc.end.offset - 1);
                        replace(closing + 2, node.tag.length, identifier);
                    }
                }
            }
            for (const prop of node.props) {
                if (prop.type !== NodeTypes.DIRECTIVE) continue;
                if (prop.name === 'slot')
                    collectBindingPatternNames(prop as DirectiveNode).forEach((name) => locals.add(name));
                if (isBuiltInDirective(prop.name)) continue;
                const binding = bindings.find((name) => normalize(name) === normalize(`v-${prop.name}`));
                if (!binding || locals.has(binding)) continue;
                const identifier = asset('directives', prop.name, binding);
                const directiveName = identifier
                    .slice(1)
                    .replace(/[A-Z]/g, (letter) => `-${letter.toLowerCase()}`)
                    .slice(1);
                replace(prop.loc.start.offset + 2, prop.name.length, directiveName);
            }
            visit(node.children, locals);
        }
    }
    visit(parse(template.content).children, new Set());
    return { edits, declarations };
}
