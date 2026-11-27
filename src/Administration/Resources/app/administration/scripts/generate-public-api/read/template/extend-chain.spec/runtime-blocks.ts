/**
 * @sw-package framework
 *
 * The blocks the runtime factory builds from a set of templates, with the origin of each block, and the line
 * format in which the runtime spec compares them with the blocks of the generator.
 */
import type { TwigToken } from 'src/core/factory/reconstruct-twig-template';
import TemplateFactory from 'src/core/factory/template.factory';
import type { TemplateBlock } from '../../../model/blocks';
import { FLAG_STATES } from '../../../model/flag-states';
import type { FlagContext } from '../../../model/flag-states';
import { isBlock, listBlocks } from '../block-tree';
import type { BlockContent } from '../block-tree';
import { templateTree } from '../template-blocks';

/**
 * @private
 *
 * A component as both sides get it: the text of its Twig template and of its override templates.
 */
export interface Component {
    name: string;
    extends?: string | null;
    template?: string | null;
    overrides?: { index: number; template: string }[];
}

/** @private */
export function fileOf(name: string): string {
    return `${name}.html.twig`;
}

/**
 * @private
 *
 * One line per block, parent and origin, so a difference names the block.
 */
export function blockLines(blocks: TemplateBlock[]): string[] {
    const states = new Map<string, Set<string>>();

    blocks.forEach((block) => {
        const key = `${block.parent ?? '(top level)'} > ${block.name}  (${block.origin ?? 'own'})`;

        states.set(key, new Set([...(states.get(key) ?? []), ...FLAG_STATES.filter((state) => block.states[state])]));
    });

    return [...states]
        .filter(([, existsIn]) => existsIn.size > 0)
        .map(([key, existsIn]) => `${key}  [${[...existsIn].sort().join(', ')}]`)
        .sort();
}

/*
 * The runtime resolves `{% parent %}` before it hands out the tokens, so the origin of a block cannot be read from
 * them. Instead every block tag gets two comments: one in front of it, which says whose template placed the block
 * there, and one at the start of its content, which travels with the content into the merged template.
 */
const ORIGIN_MARK = /<!--@origin (place|content) (\S+) (\S+)-->/g;

const BLOCK_TAG = /\{%-?\s*block\s+(\w+)\s*-?%\}/g;

interface OriginMark {
    kind: string;
    component: string;
    block: string;
}

function withOriginMarks(component: string, template: string): string {
    return template.replace(
        BLOCK_TAG,
        (tag, block: string) =>
            `<!--@origin place ${component} ${block}-->${tag}<!--@origin content ${component} ${block}-->`,
    );
}

function originMarks(text: string): OriginMark[] {
    return [...text.matchAll(ORIGIN_MARK)].map((match) => ({ kind: match[1], component: match[2], block: match[3] }));
}

function textOf(tokens: TwigToken[]): string {
    return tokens.map((token) => token.value ?? textOf(token.token?.output ?? [])).join('');
}

/** The origin of a block of `component`, by the same rules the generator follows. */
function runtimeOrigin(block: TwigToken, placedBy: string | undefined, component: Component): TemplateBlock['origin'] {
    if (!component.extends || placedBy === component.name) {
        return null;
    }

    const contentOf = originMarks(textOf(block.token?.output ?? []))
        .filter(({ kind, block: name }) => kind === 'content' && name === block.token?.blockName)
        .map(({ component: name }) => name);

    if (!contentOf.includes(component.name)) {
        return 'inherited';
    }

    return contentOf.some((name) => name !== component.name) ? 'keepsParent' : 'replaced';
}

/** Walks the tokens and the tree read from them side by side: both have the blocks in the order of the source. */
function withRuntimeOrigins(tree: BlockContent[], tokens: TwigToken[], component: Component): BlockContent[] {
    const blockTokens = tokens.flatMap((token, index) => {
        if (token.token?.blockName === undefined) {
            return [];
        }

        const before = tokens[index - 1]?.value ?? '';
        const placedBy = originMarks(before).findLast(({ kind }) => kind === 'place')?.component;

        return [{ token, placedBy }];
    });
    const blocks = tree.filter(isBlock);

    if (blocks.length !== blockTokens.length) {
        throw new Error(`The runtime template of ${component.name} has other blocks than the tree read from it.`);
    }

    return blocks.map((node, index) => {
        const { token, placedBy } = blockTokens[index];

        return {
            ...node,
            origin: runtimeOrigin(token, placedBy, component),
            content: withRuntimeOrigins(node.content, token.token?.output ?? [], component),
        };
    });
}

/** The template the runtime built, as Twig source again, so the generator can read its blocks. */
function twigSource(tokens: TwigToken[]): string {
    return tokens
        .map((token) => {
            if (token.type === 'raw') {
                return (token.value ?? '').replace(ORIGIN_MARK, '');
            }

            if (token.token?.blockName !== undefined) {
                return `{% block ${token.token.blockName} %}${twigSource(token.token.output ?? [])}{% endblock %}`;
            }

            return token.token?.type === 'parent' ? '{% parent %}' : '';
        })
        .join('');
}

/** @private */
export function runtimeBlocks(components: Component[], flags: FlagContext): Map<string, TemplateBlock[]> {
    TemplateFactory.getTemplateRegistry().clear();
    TemplateFactory.getNormalizedTemplateRegistry().clear();
    TemplateFactory.disableTwigCache();

    components.forEach(({ name, extends: parent, template, overrides = [] }) => {
        const marked = template === null || template === undefined ? null : withOriginMarks(name, template);

        if (parent) {
            TemplateFactory.extendComponentTemplate(name, parent, marked);
        } else if (marked !== null) {
            TemplateFactory.registerComponentTemplate(name, marked);
        }

        overrides.forEach((override) =>
            TemplateFactory.registerTemplateOverride(name, withOriginMarks(name, override.template), override.index),
        );
    });

    const resolved = TemplateFactory.resolveTemplates() as Map<string, { template: { tokens: TwigToken[] } }>;
    const byName = new Map(components.map((component) => [component.name, component]));

    return new Map(
        [...resolved].map(([name, { template }]) => {
            const tree = templateTree(twigSource(template.tokens), fileOf(name), flags);
            const component = byName.get(name) ?? { name };

            return [name, listBlocks(withRuntimeOrigins(tree, template.tokens, component))];
        }),
    );
}
