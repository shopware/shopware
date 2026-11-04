/**
 * @sw-package framework
 * @private
 */

import ts from 'typescript';
import { literalArgument } from './dependencies/rewrites/helpers';

/** How a component looks a mixin up, also through `Mixin` destructured from the global in any way. */
const LOOKUPS = new Set(['Mixin.getByName', 'Shopware.Mixin.getByName']);

/**
 * The mixins a component file looks up by name whose registration no file exports as its default, such as
 * `export-channel-filter`, which `src/module/sw-export-channel-tracking/index.js` registers by importing its file
 * for the side effect. A mixin module's `register.ts` does not count, because `src/` imports the mixin from the module.
 * `src/` could not import such a mixin, so the component waits until the mixin gets its own module.
 *
 * @example
 * findLocalMixins("mixins: [Mixin.getByName('sw-settings-list')]", (name) => name === 'sw-settings-list');
 * // → ['sw-settings-list']
 */
export function findLocalMixins(code: string, isModuleLocal: (name: string) => boolean): string[] {
    const source = ts.createSourceFile('component.ts', code, ts.ScriptTarget.Latest, true, ts.ScriptKind.TS);
    const names = new Set<string>();

    const visit = (node: ts.Node) => {
        if (ts.isCallExpression(node) && LOOKUPS.has(node.expression.getText(source))) {
            const name = literalArgument(node);
            if (name !== null && isModuleLocal(name)) {
                names.add(name);
            }
        }

        node.forEachChild(visit);
    };
    visit(source);

    return [...names];
}
