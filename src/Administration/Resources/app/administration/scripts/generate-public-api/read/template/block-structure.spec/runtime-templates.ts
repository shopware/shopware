/**
 * @sw-package framework
 *
 * The templates of the Administration as the runtime factory merges them, for specs that compare the snapshot
 * with what Vue gets to compile.
 */
import type { TwigToken } from 'src/core/factory/reconstruct-twig-template';
import TemplateFactory from 'src/core/factory/template.factory';
import type { ComponentUnit } from '../../../model/model';
import { ADMINISTRATION_ROOT, currentMajorFlags } from '../../../source/checkout';
import { readSourceTree } from '../../../source/source-tree';
import { buildModel } from '../../build-model';
import { componentCalls } from '../../component-registry';

/**
 * @private
 *
 * What a block renders: `content` is the block as the runtime merged it, and `occurrence` counts the blocks of
 * the template in the order they start.
 */
export type BlockRenderer = (name: string, content: string, occurrence: number) => string;

/** @private */
export interface RuntimeComponent {
    unit: ComponentUnit;
    tokens: TwigToken[];
}

/**
 * @private
 *
 * The template without its Twig tags, which is what Vue compiles. `block` can change what a block renders.
 */
export function vueTemplate(tokens: TwigToken[], block: BlockRenderer = (_name, content) => content): string {
    let occurrences = 0;
    const render = (output: TwigToken[]): string =>
        output
            .map((token) => {
                if (token.type === 'raw') {
                    return token.value ?? '';
                }

                if (token.token?.blockName === undefined) {
                    return '';
                }

                const occurrence = occurrences;

                occurrences += 1;

                return block(token.token.blockName, render(token.token.output ?? []), occurrence);
            })
            .join('');

    return render(tokens);
}

/**
 * @private
 *
 * Every component of the Administration that the runtime builds a Twig template for, with its snapshot unit.
 */
export function runtimeComponents(): RuntimeComponent[] {
    const tree = readSourceTree(ADMINISTRATION_ROOT);
    const options = { majorFlags: currentMajorFlags() };
    const model = buildModel(tree, options);
    const { registrations, overrides } = componentCalls(tree, model.flags);

    TemplateFactory.getTemplateRegistry().clear();
    TemplateFactory.getNormalizedTemplateRegistry().clear();
    TemplateFactory.disableTwigCache();

    registrations.forEach(({ name, extends: parent, template }) => {
        const source = template === null ? null : tree.read(template);

        if (parent !== null) {
            TemplateFactory.extendComponentTemplate(name, parent, source);
        } else if (source !== null) {
            TemplateFactory.registerComponentTemplate(name, source);
        }

        overrides
            .filter((override) => override.target === name && override.template !== null)
            .forEach((override) =>
                TemplateFactory.registerTemplateOverride(name, tree.read(override.template ?? ''), override.index),
            );
    });

    const resolved = TemplateFactory.resolveTemplates() as Map<string, { template: { tokens: TwigToken[] } }>;

    return model.components.flatMap((unit) => {
        const tokens = resolved.get(unit.name)?.template.tokens;

        return tokens === undefined ? [] : [{ unit, tokens }];
    });
}
