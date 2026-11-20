/**
 * @sw-package framework
 *
 * Prints `index.json`, the components with their file, visibility, parent and block names for tools.
 */
import { byCodePoint } from './code-point-order';
import type { Model } from './model';

/** @private */
export function printIndex({ components }: Model): string {
    const entries = components.map((unit): [string, object] => [
        unit.name,
        {
            file: unit.file,
            visibility: unit.visibility,
            extends: unit.extends,
            blocks: [...new Set(unit.blocks.map((block) => block.name))].sort(byCodePoint),
        },
    ]);

    return `${JSON.stringify({ components: Object.fromEntries(entries) }, null, 4)}\n`;
}
