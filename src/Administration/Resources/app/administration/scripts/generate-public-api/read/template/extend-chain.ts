/**
 * @sw-package framework
 *
 * Builds the template a component renders: its own blocks merged into those of the component it extends, and
 * the core overrides applied on top. The rules are those of `src/core/factory/template.factory.js`, which
 * differ from plain Twig. `extend-chain.spec/runtime.spec.ts` compares the result with what that factory builds.
 */
import type { BlockOrigin } from '../../model/blocks';
import { inBothStates } from '../../model/flag-states';
import type { FlagStates } from '../../model/flag-states';
import type { DroppedBlock } from '../../model/model';
import { hasBlock, isBlock, isMarkup, placeContent } from './block-tree';
import type { BlockContent, BlockNode, ParentCall } from './block-tree';

/** @private */
export interface TemplateSource {
    file: string;
    tree: BlockContent[];
}

/**
 * @private
 *
 * The template of a core override, with the index the runtime orders the overrides of a component by.
 */
export interface OverrideTemplate {
    index: number;
    template: TemplateSource;
}

/**
 * @private
 *
 * A component with its Twig template, if it has one, and the templates of its core overrides in file order.
 * A `native` component keeps its blocks in a `.vue` file instead. `states` are the flag states in which the
 * component is registered.
 */
export interface ComponentTemplate {
    name: string;
    extends: string | null;
    native: boolean;
    states: FlagStates;
    template: TemplateSource | null;
    overrides: OverrideTemplate[];
}

/**
 * @private
 *
 * `states` are the flag states in which the component and every component it extends are registered.
 */
export interface ResolvedTemplate {
    tree: BlockContent[];
    dropped: DroppedBlock[];
    states: FlagStates;
}

type MergedTemplate = Omit<ResolvedTemplate, 'states'>;

type ExtendChild = ComponentTemplate & { extends: string };

/** A declaration of a child, resolved against the content of the block it replaces. */
interface Redeclaration {
    content: BlockContent[];
    keepsParent: boolean;
}

function isParentCall(item: BlockContent): item is ParentCall {
    return item.kind === 'parent';
}

function callsParent(content: BlockContent[]): boolean {
    return content.some(isParentCall);
}

function withParentContent(content: BlockContent[], replaced: BlockContent[]): BlockContent[] {
    return content.flatMap((item) =>
        isParentCall(item)
            ? replaced.map((kept) => (isMarkup(kept) ? kept : { ...kept, within: inBothStates(kept.within, item.within) }))
            : [item],
    );
}

/**
 * `mergeTokens`: `{% parent %}` takes the content of the replaced block, directly in the redeclaration or in a
 * block directly inside it. Deeper `{% parent %}` tags stay unresolved.
 */
function redeclarationOf(replaced: BlockContent[], declared: BlockContent[]): Redeclaration {
    const oneBlockDeep = declared.map((item) =>
        isBlock(item) ? { ...item, content: withParentContent(item.content, replaced) } : item,
    );

    return {
        content: withParentContent(oneBlockDeep, replaced),
        keepsParent: callsParent(declared) || declared.filter(isBlock).some(({ content }) => callsParent(content)),
    };
}

/** An extend child that redeclares what its parent redeclared with `{% parent %}` keeps what the parent kept. */
function originAfterRedeclaration(previous: BlockOrigin | null, keepsParent: boolean): BlockOrigin | null {
    if (previous === null) {
        return null;
    }

    return previous === 'replaced' || !keepsParent ? 'replaced' : 'keepsParent';
}

/**
 * `resolveToken` and `resolveTokens`: every block of the tree that has a declaration is replaced by it, and the
 * walk does not continue below a replaced block. A block that occurs several times gets the content that the
 * declaration has after its first replacement.
 */
function redeclare(
    tree: BlockContent[],
    declarations: ReadonlyMap<string, BlockNode>,
): { tree: BlockContent[]; replaced: Set<string> } {
    const redeclarations = new Map<string, Redeclaration>();
    const resolve = (item: BlockContent): BlockContent => {
        if (!isBlock(item)) {
            return item;
        }

        const declaration = declarations.get(item.name);

        if (declaration === undefined) {
            return { ...item, content: item.content.map(resolve) };
        }

        const redeclaration = redeclarations.get(item.name) ?? redeclarationOf(item.content, declaration.content);

        redeclarations.set(item.name, redeclaration);

        // Only the content of a declaration moves. The conditions around the declaration itself stay behind.
        return {
            ...item,
            origin: originAfterRedeclaration(item.origin, redeclaration.keepsParent),
            content: placeContent(redeclaration.content, item.states),
            at: declaration.at,
            markers: [...item.markers, ...declaration.markers],
        };
    };

    return { tree: tree.map(resolve), replaced: new Set(redeclarations.keys()) };
}

function inherited(content: BlockContent[]): BlockContent[] {
    return content.map((item) =>
        isBlock(item) ? { ...item, origin: 'inherited', content: inherited(item.content) } : item,
    );
}

/** Applies the declarations and reports those that replaced nothing. */
function applyDeclarations(
    tree: BlockContent[],
    declarations: ReadonlyMap<string, BlockNode>,
    file: string,
): MergedTemplate {
    const { tree: merged, replaced } = redeclare(tree, declarations);
    const dropped = [...declarations.keys()]
        .filter((block) => !replaced.has(block))
        .map((block): DroppedBlock => ({ kind: hasBlock(tree, block) ? 'shadowed' : 'missing', block, file }));

    return { tree: merged, dropped };
}

/**
 * `resolveExtendTokens`: only the top-level blocks of the child are declarations, and the first one of a name
 * wins. `normalizeTokens` replaces a top-level block that the parent does not have by its content, once, so a
 * child can wrap its declarations.
 */
function topLevelDeclarations(
    { file, tree }: TemplateSource,
    parent: BlockContent[],
): { declarations: Map<string, BlockNode>; dropped: DroppedBlock[] } {
    const declarations = new Map<string, BlockNode>();
    const dropped: DroppedBlock[] = [];

    tree.filter(isBlock).forEach((block) => {
        const declared = hasBlock(parent, block.name) ? [block] : block.content.filter(isBlock);

        if (declared.length === 0) {
            dropped.push({ kind: 'missing', block: block.name, file });
        }

        declared.forEach((declaration) => {
            if (declarations.has(declaration.name)) {
                dropped.push({ kind: 'duplicate', block: declaration.name, file });
            } else {
                declarations.set(declaration.name, declaration);
            }
        });
    });

    return { declarations, dropped };
}

function extendTemplate(template: TemplateSource | null, parent: BlockContent[]): MergedTemplate {
    if (template === null) {
        return { tree: inherited(parent), dropped: [] };
    }

    const { declarations, dropped } = topLevelDeclarations(template, parent);
    const extended = applyDeclarations(inherited(parent), declarations, template.file);

    return { tree: extended.tree, dropped: [...dropped, ...extended.dropped] };
}

/** `applyTemplateOverrides`: by index. Overrides with the same index keep the order they come in. */
function overridesInOrder({ overrides }: ComponentTemplate): TemplateSource[] {
    return [...overrides].sort((a, b) => a.index - b.index).map(({ template }) => template);
}

function rootTemplate({ template, states }: ComponentTemplate): ResolvedTemplate {
    return { tree: template?.tree ?? [], dropped: [], states };
}

function childTemplate(child: ExtendChild, parent: ResolvedTemplate): ResolvedTemplate {
    return { ...extendTemplate(child.template, parent.tree), states: inBothStates(child.states, parent.states) };
}

/**
 * `applyTemplateOverrides`: the top-level blocks of an override are applied one after another, so a later one
 * can replace what an earlier one kept or added. A block that the component does not have is not unwrapped.
 */
function overrideTemplate(base: ResolvedTemplate, { file, tree }: TemplateSource): ResolvedTemplate {
    return tree.filter(isBlock).reduce((resolved, block) => {
        const overridden = applyDeclarations(resolved.tree, new Map([[block.name, block]]), file);

        return { ...resolved, tree: overridden.tree, dropped: [...resolved.dropped, ...overridden.dropped] };
    }, base);
}

function declaresBlocks(template: TemplateSource | null): template is TemplateSource {
    return template !== null && template.tree.some(isBlock);
}

function isExtendChild(component: ComponentTemplate): component is ExtendChild {
    return component.extends !== null;
}

function parentOf(child: ExtendChild, byName: ReadonlyMap<string, ComponentTemplate>): ComponentTemplate {
    const parent = byName.get(child.extends);

    if (parent === undefined) {
        throw new Error(`${child.name} extends ${child.extends}, which is not registered.`);
    }

    return parent;
}

function assertExtendable(child: ExtendChild, parent: ComponentTemplate, chain: readonly string[]): void {
    if (chain.includes(parent.name)) {
        throw new Error(`${child.name} extends ${parent.name}, which extends it in turn.`);
    }

    if (child.native || parent.native) {
        throw new Error(
            `${child.name} extends ${parent.name}, and one of them keeps its blocks in a .vue file. ` +
                'The snapshot can only merge the blocks of Twig templates along an extend chain so far.',
        );
    }

    if (parent.template === null && parent.extends === null && declaresBlocks(child.template)) {
        throw new Error(
            `${child.name} extends ${parent.name}, which has no Twig template. ` +
                `The runtime drops the template of ${child.name} then, so its blocks would never render.`,
        );
    }
}

function extendableParent(
    child: ExtendChild,
    byName: ReadonlyMap<string, ComponentTemplate>,
    chain: readonly string[],
): ComponentTemplate {
    const parent = parentOf(child, byName);

    assertExtendable(child, parent, chain);

    return parent;
}

function assertOverridable(component: ComponentTemplate): void {
    const hasTwigTemplate = !component.native && (component.template !== null || component.extends !== null);

    if (!hasTwigTemplate && component.overrides.some(({ template }) => declaresBlocks(template))) {
        throw new Error(
            `A core override of ${component.name} brings a Twig template, but ${component.name} has none. ` +
                'The snapshot cannot tell which blocks the override replaces.',
        );
    }
}

/**
 * @private
 *
 * The template every component renders, in the order of `templates`. `registerNormalizedTemplate` resolves the
 * component a child extends with its overrides first, and applies the overrides of the child last.
 */
export function resolveTemplates(templates: readonly ComponentTemplate[]): ResolvedTemplate[] {
    const byName = new Map(templates.map((template) => [template.name, template]));
    const resolved = new Map<string, ResolvedTemplate>();

    const resolve = (component: ComponentTemplate, chain: readonly string[]): ResolvedTemplate => {
        const known = resolved.get(component.name);

        if (known !== undefined) {
            return known;
        }

        assertOverridable(component);

        const own = isExtendChild(component)
            ? childTemplate(component, resolve(extendableParent(component, byName, chain), [...chain, component.name]))
            : rootTemplate(component);
        const template = overridesInOrder(component).reduce(overrideTemplate, own);

        resolved.set(component.name, template);

        return template;
    };

    return templates.map((template) => resolve(template, []));
}
