/**
 * @sw-package framework
 *
 * Prints `index.json` for tools: the components with their file, visibility, parent and block names, and the
 * services and stores with their file and visibility.
 */
import { byCodePoint } from '../helpers/code-point-order';
import type { Model } from '../model/model';
import { registeredIndexEntries } from './print-registered-reports';

/** @private */
export function printIndex(model: Model): string {
    const entries = model.components.map((unit): [string, object] => [
        unit.name,
        {
            file: unit.file,
            visibility: unit.visibility,
            extends: unit.extends,
            blocks: [...new Set(unit.blocks.map((block) => block.name))].sort(byCodePoint),
        },
    ]);

    return `${JSON.stringify(
        {
            components: Object.fromEntries(entries),
            ...registeredIndexEntries(model),
        },
        null,
        4,
    )}\n`;
}
