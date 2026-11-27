/**
 * @sw-package framework
 *
 * Prints `index.json` for tools: the components with their file, visibility, parent and block names, and the
 * services with their file and visibility.
 */
import { byCodePoint } from '../helpers/code-point-order';
import type { Model } from '../model/model';

/** @private */
export function printIndex({ components, services }: Model): string {
    const serviceEntries = services.map(({ name, file, visibility }): [string, object] => [name, { file, visibility }]);
    const entries = components.map((unit): [string, object] => [
        unit.name,
        {
            file: unit.file,
            visibility: unit.visibility,
            extends: unit.extends,
            blocks: [...new Set(unit.blocks.map((block) => block.name))].sort(byCodePoint),
        },
    ]);

    return `${JSON.stringify(
        { components: Object.fromEntries(entries), services: Object.fromEntries(serviceEntries) },
        null,
        4,
    )}\n`;
}
