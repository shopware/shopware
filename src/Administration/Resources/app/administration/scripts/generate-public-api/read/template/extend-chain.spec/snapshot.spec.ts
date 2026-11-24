/**
 * @sw-package framework
 *
 * How the snapshot shows an extend child and a core override. `runtime.spec.ts` checks that the merged blocks
 * are the ones the runtime builds.
 */
import { snapshotOf } from '../../../snapshot/snapshot';
import { sourceTreeOf } from '../../../source/source-tree';
import { buildModel } from '../../build-model';

const OPTIONS = { majorFlags: ['v6.8.0.0'] };

const REGISTRY = 'src/module/sw-example/index.js';

const MAJOR = "feature.isActive('v6.8.0.0')";

const CARD_TEMPLATE = [
    '{% block sw_example_card %}',
    '    {% block sw_example_card_header %}{% block sw_example_card_title %}{% endblock %}{% endblock %}',
    '    {% block sw_example_card_body %}{% block sw_example_card_text %}{% endblock %}{% endblock %}',
    '    {% block sw_example_card_footer %}{% endblock %}',
    '{% endblock %}',
].join('\n');

function folder(name: string): string {
    return `src/module/sw-example/component/${name}`;
}

/** The files of a component. Without a template its script has none either. */
function component(name: string, template: string | null): Record<string, string> {
    if (template === null) {
        return { [`${folder(name)}/index.js`]: 'export default {};' };
    }

    return {
        [`${folder(name)}/index.js`]: `import template from './${name}.html.twig';\n\nexport default { template };`,
        [`${folder(name)}/${name}.html.twig`]: template,
    };
}

function register(name: string): string {
    return `Shopware.Component.register('${name}', () => import('./component/${name}'));`;
}

function extend(name: string, parent: string): string {
    return `Shopware.Component.extend('${name}', '${parent}', () => import('./component/${name}'));`;
}

/** A core override in a file of its own, as the modules write them. */
function override(target: string, name: string, template: string, index = ''): Record<string, string> {
    return {
        [`src/module/sw-example/extension/${name}/index.js`]: [
            `import template from './${name}.html.twig';`,
            '',
            `Shopware.Component.override('${target}', { template }${index});`,
        ].join('\n'),
        [`src/module/sw-example/extension/${name}/${name}.html.twig`]: template,
    };
}

function sourceTree(registry: string[], files: Record<string, string>) {
    const sources: Record<string, string> = { [REGISTRY]: registry.join('\n'), ...files };

    return sourceTreeOf(Object.keys(sources), (file) => sources[file]);
}

/** A card, a tile that extends it with `tileTemplate`, and whatever `files` and `registry` add. */
function cardAndTile(tileTemplate: string | null, files: Record<string, string> = {}, registry: string[] = []) {
    return sourceTree(
        [
            register('sw-example-card'),
            extend('sw-example-tile', 'sw-example-card'),
            ...registry,
        ],
        { ...component('sw-example-card', CARD_TEMPLATE), ...component('sw-example-tile', tileTemplate), ...files },
    );
}

/** The lines of the `blocks` section of a component's report, with their indentation. */
function blockLines(tree: ReturnType<typeof sourceTree>, name: string): string[] {
    const report = snapshotOf(tree, OPTIONS).files[`module/sw-example/component/${name}.api.md`];
    const [, section = ''] = /\nblocks\n([\s\S]*?)\n(?:\n|```)/.exec(report) ?? [];

    return section.split('\n');
}

function indexedBlocks(tree: ReturnType<typeof sourceTree>, name: string): string[] {
    const { components } = JSON.parse(snapshotOf(tree, OPTIONS).files['index.json']) as {
        components: Record<string, { blocks: string[] }>;
    };

    return components[name].blocks;
}

function droppedBlocks(tree: ReturnType<typeof sourceTree>): string[] {
    return buildModel(tree, OPTIONS).droppedBlocks.map(({ kind, block }) => `${kind} ${block}`);
}

describe('scripts/generate-public-api/read/template/extend-chain in the snapshot', () => {
    describe('an extend child', () => {
        const tile = cardAndTile(
            [
                '{% block sw_example_card_header %}<h2>Tile</h2>{% endblock %}',
                '{% block sw_example_card_body %}{% parent %}{% block sw_example_tile_badge %}{% endblock %}{% endblock %}',
            ].join('\n'),
        );

        it('lists the blocks of the template it renders and says how it got each of them', () => {
            expect(blockLines(tile, 'sw-example-tile')).toEqual([
                '  sw_example_card  [inherited]',
                '    sw_example_card_body  [keepsParent]',
                '      sw_example_card_text  [inherited]',
                '      sw_example_tile_badge',
                '    sw_example_card_footer  [inherited]',
                '    sw_example_card_header  [replaced]',
            ]);
        });

        it('leaves the report of its parent as it is', () => {
            expect(blockLines(tile, 'sw-example-card')).toEqual([
                '  sw_example_card',
                '    sw_example_card_body',
                '      sw_example_card_text',
                '    sw_example_card_footer',
                '    sw_example_card_header',
                '      sw_example_card_title',
            ]);
        });

        it('lists the same blocks in the index', () => {
            expect(indexedBlocks(tile, 'sw-example-tile')).toEqual([
                'sw_example_card',
                'sw_example_card_body',
                'sw_example_card_footer',
                'sw_example_card_header',
                'sw_example_card_text',
                'sw_example_tile_badge',
            ]);
        });

        it('inherits every block when it has no template', () => {
            expect(blockLines(cardAndTile(null), 'sw-example-tile')).toEqual([
                '  sw_example_card  [inherited]',
                '    sw_example_card_body  [inherited]',
                '      sw_example_card_text  [inherited]',
                '    sw_example_card_footer  [inherited]',
                '    sw_example_card_header  [inherited]',
                '      sw_example_card_title  [inherited]',
            ]);
        });

        it('marks what it inherits by its direct parent, whatever that parent did with the block', () => {
            const tree = cardAndTile(
                '{% block sw_example_card_header %}{% block sw_example_tile_badge %}{% endblock %}{% endblock %}',
                component('sw-example-teaser', '{% block sw_example_tile_badge %}New{% endblock %}'),
                [extend('sw-example-teaser', 'sw-example-tile')],
            );

            expect(blockLines(tree, 'sw-example-teaser').slice(-2)).toEqual([
                '    sw_example_card_header  [inherited]',
                '      sw_example_tile_badge  [replaced]',
            ]);
        });

        it('keeps the inherited blocks of a private parent public', () => {
            const tree = cardAndTile(null, {
                [`${folder('sw-example-card')}/index.js`]:
                    "import template from './sw-example-card.html.twig';\n\n/**\n * @private\n */\nexport default { template };",
            });

            expect(blockLines(tree, 'sw-example-card')[0]).toBe('  sw_example_card  [private]');
            expect(blockLines(tree, 'sw-example-tile')[0]).toBe('  sw_example_card  [inherited]');
        });
    });

    describe('flag states', () => {
        const renamedInMajor = [
            '{% block sw_example_card %}',
            `<template v-if="${MAJOR}">{% block sw_example_card_mt_tabs %}{% endblock %}</template>`,
            '<template v-else>{% block sw_example_card_tabs %}{% endblock %}</template>',
            '{% endblock %}',
        ].join('\n');

        it('shows that a redeclared block is gone in the state in which the parent renamed it', () => {
            const tree = cardAndTile(
                '{% block sw_example_card_tabs %}{% block sw_example_tile_checkbox %}{% endblock %}{% endblock %}',
                component('sw-example-card', renamedInMajor),
            );

            expect(blockLines(tree, 'sw-example-tile')).toEqual([
                '  sw_example_card  [inherited]',
                '    sw_example_card_mt_tabs  [inherited] [from v6.8.0]',
                '    sw_example_card_tabs  [replaced] [until v6.8.0]',
                '      sw_example_tile_checkbox  [until v6.8.0]',
            ]);
        });

        it('limits a child to the flag states in which its parent is registered', () => {
            const tree = sourceTree(
                [
                    "if (!Shopware.Feature.isActive('v6.8.0.0')) {",
                    register('sw-example-card'),
                    '}',
                    extend('sw-example-tile', 'sw-example-card'),
                ],
                { ...component('sw-example-card', CARD_TEMPLATE), ...component('sw-example-tile', null) },
            );

            expect(blockLines(tree, 'sw-example-tile')[0]).toBe('  sw_example_card  [inherited] [until v6.8.0]');
        });
    });

    describe('a core override', () => {
        const overridden = cardAndTile(
            '{% block sw_example_card_notice %}Tile{% endblock %}',
            override(
                'sw-example-card',
                'sw-example-card-notice',
                '{% block sw_example_card_footer %}{% parent %}{% block sw_example_card_notice %}{% endblock %}{% endblock %}',
            ),
        );

        it('adds its blocks to the component it overrides, which has no origin for them', () => {
            expect(blockLines(overridden, 'sw-example-card').slice(3, 5)).toEqual([
                '    sw_example_card_footer',
                '      sw_example_card_notice',
            ]);
        });

        it('applies before a child extends the component', () => {
            expect(blockLines(overridden, 'sw-example-tile').slice(3, 5)).toEqual([
                '    sw_example_card_footer  [inherited]',
                '      sw_example_card_notice  [replaced]',
            ]);
        });

        it.each([
            ['with {% parent %}', '{% parent %}', '[keepsParent]'],
            ['without {% parent %}', '', '[replaced]'],
        ])('counts %s like a redeclaration of the child when it overrides the child', (_case, parent, marker) => {
            const tree = cardAndTile(
                null,
                override(
                    'sw-example-tile',
                    'sw-example-tile-header',
                    `{% block sw_example_card_header %}${parent}{% endblock %}`,
                ),
            );

            expect(blockLines(tree, 'sw-example-tile')).toContain(`    sw_example_card_header  ${marker}`);
        });

        it('keeps a block replaced that the child replaced, even when the override keeps the content of the child', () => {
            const tree = cardAndTile(
                '{% block sw_example_card_header %}Tile{% endblock %}',
                override(
                    'sw-example-tile',
                    'sw-example-tile-header',
                    '{% block sw_example_card_header %}{% parent %}{% endblock %}',
                ),
            );

            expect(blockLines(tree, 'sw-example-tile')).toContain('    sw_example_card_header  [replaced]');
        });

        it('applies the overrides of a component by their index', () => {
            const tree = cardAndTile(null, {
                ...override(
                    'sw-example-card',
                    'sw-example-card-late',
                    '{% block sw_example_card_early %}Late{% endblock %}',
                    ', 5',
                ),
                ...override(
                    'sw-example-card',
                    'sw-example-card-zz-early',
                    '{% block sw_example_card_footer %}{% block sw_example_card_early %}{% endblock %}{% endblock %}',
                    ', -1',
                ),
            });

            expect(droppedBlocks(tree)).toEqual([]);
            expect(indexedBlocks(tree, 'sw-example-card')).toContain('sw_example_card_early');
        });
    });

    describe('blocks the runtime drops', () => {
        it.each([
            [
                'a block the parent does not have',
                '{% block sw_example_card_sidebar %}Tile{% endblock %}',
                'missing sw_example_card_sidebar',
            ],
            [
                'a block below a block the same template redeclares',
                '{% block sw_example_card_header %}{% parent %}{% endblock %}{% block sw_example_card_title %}Tile{% endblock %}',
                'shadowed sw_example_card_title',
            ],
            [
                'a block the template declares twice',
                '{% block sw_example_card_footer %}One{% endblock %}{% block sw_example_card_footer %}Two{% endblock %}',
                'duplicate sw_example_card_footer',
            ],
            [
                'an unknown block inside a wrapper',
                '{% block sw_example_tile %}{% block sw_example_card_footer %}Tile{% endblock %}{% block sw_example_tile_extra %}{% endblock %}{% endblock %}',
                'missing sw_example_tile_extra',
            ],
        ])('reports %s', (_case, template, dropped) => {
            expect(droppedBlocks(cardAndTile(template))).toEqual([dropped]);
        });

        it('lists neither a dropped block nor the wrapper around the declarations of a child', () => {
            const tree = cardAndTile(
                '{% block sw_example_tile %}{% block sw_example_card_footer %}Tile{% endblock %}{% block sw_example_tile_extra %}{% endblock %}{% endblock %}',
            );

            expect(blockLines(tree, 'sw-example-tile').slice(3, 5)).toEqual([
                '    sw_example_card_footer  [replaced]',
                '    sw_example_card_header  [inherited]',
            ]);
            expect(indexedBlocks(tree, 'sw-example-tile')).toEqual(indexedBlocks(tree, 'sw-example-card'));
        });

        it('reports a block of an override that the component does not have', () => {
            const tree = cardAndTile(
                null,
                override('sw-example-card', 'sw-example-card-sidebar', '{% block sw_example_card_sidebar %}{% endblock %}'),
            );

            expect(droppedBlocks(tree)).toEqual(['missing sw_example_card_sidebar']);
            expect(indexedBlocks(tree, 'sw-example-card')).not.toContain('sw_example_card_sidebar');
        });
    });

    describe('chains it cannot read', () => {
        it.each([
            [
                'a child of a component that is not registered',
                sourceTree([extend('sw-example-tile', 'sw-example-card')], component('sw-example-tile', null)),
                'sw-example-tile extends sw-example-card, which is not registered.',
            ],
            [
                'two components that extend each other',
                sourceTree([extend('sw-example-card', 'sw-example-tile'), extend('sw-example-tile', 'sw-example-card')], {
                    ...component('sw-example-card', null),
                    ...component('sw-example-tile', null),
                }),
                'sw-example-tile extends sw-example-card, which extends it in turn.',
            ],
            [
                'a child with blocks whose parent has no Twig template',
                sourceTree([register('sw-example-card'), extend('sw-example-tile', 'sw-example-card')], {
                    ...component('sw-example-card', null),
                    ...component('sw-example-tile', '{% block sw_example_tile %}{% endblock %}'),
                }),
                'sw-example-tile extends sw-example-card, which has no Twig template.',
            ],
            [
                'a child of a component that keeps its blocks in a .vue file',
                sourceTree([register('sw-example-card'), extend('sw-example-tile', 'sw-example-card')], {
                    [`${folder('sw-example-card')}/index.js`]: "export { default } from './sw-example-card.vue';",
                    [`${folder('sw-example-card')}/sw-example-card.vue`]:
                        '<template><sw-block name="sw_example_card">Card</sw-block></template>',
                    ...component('sw-example-tile', null),
                }),
                'sw-example-tile extends sw-example-card, and one of them keeps its blocks in a .vue file.',
            ],
            [
                'an override with a template for a component without one',
                sourceTree([register('sw-example-card')], {
                    ...component('sw-example-card', null),
                    ...override('sw-example-card', 'sw-example-card-extra', '{% block sw_example_card %}{% endblock %}'),
                }),
                'A core override of sw-example-card brings a Twig template, but sw-example-card has none.',
            ],
            [
                'an override of a component that is not registered',
                sourceTree(
                    [],
                    override('sw-example-card', 'sw-example-card-extra', '{% block sw_example_card %}{% endblock %}'),
                ),
                "Component.override('sw-example-card') overrides a component that core does not register.",
            ],
            [
                'an override that is registered in one flag state only',
                cardAndTile(null, {
                    'src/module/sw-example/extension/index.js': [
                        "if (Shopware.Feature.isActive('v6.8.0.0')) {",
                        "    Shopware.Component.override('sw-example-card', {});",
                        '}',
                    ].join('\n'),
                }),
                'the override of sw-example-card is registered in one flag state only. ' +
                    'The snapshot cannot apply an override to one state of a template so far. ' +
                    `Register the override in both states and branch its template with v-if="${MAJOR}".`,
            ],
            [
                'an override whose index is not a number',
                cardAndTile(null, {
                    'src/module/sw-example/extension/index.js':
                        "Shopware.Component.override('sw-example-card', {}, position);",
                }),
                "Component.override('sw-example-card') needs its index as a number",
            ],
        ])('rejects %s', (_case, tree, message) => {
            expect(() => snapshotOf(tree, OPTIONS)).toThrow(message);
        });
    });
});
