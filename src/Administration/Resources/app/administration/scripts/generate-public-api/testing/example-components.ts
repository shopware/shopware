/**
 * @sw-package framework
 *
 * Small source trees for the specs: the component `sw-example-card`, given by its templates or by the parts of
 * its script, and on demand `sw-example-tile` that extends it.
 */
import { sourceTreeOf } from '../source/source-tree';
import type { SourceTree } from '../source/source-tree';

/** @private */
export const OPTIONS = { majorFlags: ['v6.8.0.0'] };

/** @private */
export const MAJOR = "feature.isActive('v6.8.0.0')";

const REGISTRY = 'src/module/sw-example/index.js';

const FOLDER = 'src/module/sw-example/component';

const CARD_REGISTRATION = "Shopware.Component.register('sw-example-card', () => import('./component/sw-example-card'));";

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
        [REGISTRY]: [
            CARD_REGISTRATION,
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

/** @private */
export function cardTree({ options = '', script, preamble = '', template = '', extension = 'js' }: Card): SourceTree {
    return treeOf({
        [REGISTRY]: CARD_REGISTRATION,
        [`${FOLDER}/sw-example-card/index.${extension}`]:
            script ??
            [
                "import template from './sw-example-card.html.twig';",
                preamble,
                `export default { template, ${options} };`,
            ].join('\n'),
        [CARD_FILE]: template,
    });
}
