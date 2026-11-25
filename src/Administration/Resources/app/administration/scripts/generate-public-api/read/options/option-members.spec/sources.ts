/**
 * @sw-package framework
 *
 * The model and the report of the example card, for the specs of the component members.
 */
import type { ComponentUnit } from '../../../model/model';
import { snapshotOf } from '../../../snapshot/snapshot';
import { cardTree, OPTIONS } from '../../../testing/example-components';
import type { Card } from '../../../testing/example-components';
import { buildModel } from '../../build-model';

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
