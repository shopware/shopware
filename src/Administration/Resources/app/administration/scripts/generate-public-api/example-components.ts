/**
 * @sw-package framework
 *
 * A small source tree for the specs: the component `sw-example-card` and, on demand, `sw-example-tile` that
 * extends it.
 */
import { sourceTreeOf } from './source-tree';
import type { SourceTree } from './source-tree';

/** @private */
export const OPTIONS = { majorFlags: ['v6.8.0.0'] };

/** @private */
export const MAJOR = "feature.isActive('v6.8.0.0')";

const FOLDER = 'src/module/sw-example/component';

/** @private */
export const CARD_FILE = `${FOLDER}/sw-example-card/sw-example-card.html.twig`;

/**
 * @private
 *
 * The templates of the card and of the tile. Without `tile` only the card is registered.
 */
export interface ExampleSources {
    card: string;
    tile?: string;
    privateCard?: boolean;
}

function component(name: string, template: string, isPrivate = false): Record<string, string> {
    return {
        [`${FOLDER}/${name}/index.js`]: [
            `import template from './${name}.html.twig';`,
            '',
            ...(isPrivate ? ['/** @private */'] : []),
            'export default { template };',
        ].join('\n'),
        [`${FOLDER}/${name}/${name}.html.twig`]: template,
    };
}

/** @private */
export function treeOf(files: Record<string, string>): SourceTree {
    return sourceTreeOf(Object.keys(files), (file) => files[file]);
}

/** @private */
export function exampleTree({ card, tile, privateCard }: ExampleSources): SourceTree {
    return treeOf({
        'src/module/sw-example/index.js': [
            "Shopware.Component.register('sw-example-card', () => import('./component/sw-example-card'));",
            ...(tile === undefined
                ? []
                : [
                      "Shopware.Component.extend('sw-example-tile', 'sw-example-card', () => import('./component/sw-example-tile'));",
                  ]),
        ].join('\n'),
        ...component('sw-example-card', card, privateCard),
        ...(tile === undefined ? {} : component('sw-example-tile', tile)),
    });
}
