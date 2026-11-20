/**
 * @sw-package framework
 *
 * The example card with an options object, for the specs of the component members.
 */
import { classifyChanges } from '../break-check';
import { OPTIONS, treeOf } from '../example-components';
import type { Finding } from '../findings';
import { buildModel } from '../model';
import type { ComponentUnit } from '../model';
import { snapshotOf } from '../snapshot';
import type { SourceTree } from '../source-tree';

const FOLDER = 'src/module/sw-example/component/sw-example-card';

const CARD_TEMPLATE = `${FOLDER}/sw-example-card.html.twig`;

/**
 * @private
 *
 * What a card is made of. `options` is the body of its options object, `script` the whole file instead,
 * `preamble` what stands before the export, and `template` its Twig template.
 */
export interface Card {
    options?: string;
    script?: string;
    preamble?: string;
    template?: string;
    extension?: 'js' | 'ts';
}

function cardTree({ options = '', script, preamble = '', template = '', extension = 'js' }: Card): SourceTree {
    const file = `${FOLDER}/index.${extension}`;

    return treeOf({
        'src/module/sw-example/index.js':
            "Shopware.Component.register('sw-example-card', () => import('./component/sw-example-card'));",
        [file]:
            script ??
            [
                "import template from './sw-example-card.html.twig';",
                preamble,
                `export default { template, ${options} };`,
            ].join('\n'),
        [CARD_TEMPLATE]: template,
    });
}

/** @private */
export function cardUnit(card: Card): ComponentUnit {
    return buildModel(cardTree(card), OPTIONS).components[0];
}

/** @private */
export function reportOf(card: Card): string {
    return snapshotOf(cardTree(card), OPTIONS).files['module/sw-example/component/sw-example-card.api.md'];
}

/**
 * @private
 *
 * The lines of a section of the report of the card, without the indentation of the section.
 */
export function section(heading: string, card: Card): string[] {
    const report = reportOf(card);
    const [, lines = ''] = new RegExp(`\\n${heading}\\n([\\s\\S]*?)\\n(?:\\n|\`\`\`)`).exec(report) ?? [];

    return lines === '' ? [] : lines.split('\n').map((line) => line.slice(2));
}

/**
 * @private
 *
 * The findings for a change of the card.
 */
export function check(before: Card, after: Card): Finding[] {
    const model = (card: Card) => buildModel(cardTree(card), OPTIONS);

    return classifyChanges(model(before), model(after));
}

/**
 * @private
 *
 * One line per finding: its group, its rule and its element below the card.
 */
export function summary(findings: Finding[]): string[] {
    return findings.map(
        ({ group, rule, element }) => `${group}  ${rule}  ${element.replace('component:sw-example-card/', '')}`,
    );
}
