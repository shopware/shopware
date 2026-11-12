/**
 * @sw-package framework
 */
import { snapshotOf } from './snapshot';
import { sourceTreeOf } from './source-tree';

const REGISTRY = 'src/module/sw-example/index.js';

const CARD = 'src/module/sw-example/component/sw-example-card';

/**
 * The snapshot of a source tree given as file contents. `sw-example-card` is registered unless `files`
 * brings its own registry.
 */
function snapshot(files: Record<string, string>): Record<string, string> {
    const sources: Record<string, string> = {
        [REGISTRY]: "Shopware.Component.register('sw-example-card', () => import('./component/sw-example-card'));",
        [`${CARD}/index.js`]: "import template from './sw-example-card.html.twig';\n\nexport default { template };",
        ...files,
    };

    return snapshotOf(
        sourceTreeOf(Object.keys(sources), (file) => sources[file]),
        { majorFlags: ['v6.8.0.0', 'EXAMPLE_REWORK'] },
    ).files;
}

function card(template: string, files: Record<string, string> = {}): Record<string, string> {
    return snapshot({ [`${CARD}/sw-example-card.html.twig`]: template, ...files });
}

interface IndexedComponent {
    file: string;
    visibility: string;
    extends: string | null;
    blocks: string[];
}

function indexedComponents(files: Record<string, string>): Record<string, IndexedComponent> {
    return (JSON.parse(files['index.json']) as { components: Record<string, IndexedComponent> }).components;
}

/** The lines of the `blocks` section of the card's report, without their indentation. */
function blockLines(template: string): string[] {
    const report = card(template)['module/sw-example/component/sw-example-card.api.md'];
    const [, section = ''] = /\nblocks\n([\s\S]*?)\n(?:\n|```)/.exec(report) ?? [];

    return section.split('\n').map((line) => line.trim());
}

describe('scripts/generate-public-api/snapshot', () => {
    describe('the files of a component', () => {
        it('writes a contract, a report and an index entry beside the folder of the component', () => {
            const files = card('{% block sw_example_card %}{% block sw_example_card_title %}{% endblock %}{% endblock %}');

            expect(Object.keys(files).sort()).toEqual([
                'index.json',
                'module/sw-example/component/sw-example-card.api.md',
                'module/sw-example/component/sw-example-card.d.ts',
            ]);
            expect(JSON.parse(files['index.json'])).toEqual({
                components: {
                    'sw-example-card': {
                        file: 'module/sw-example/component/sw-example-card',
                        visibility: 'public',
                        extends: null,
                        blocks: [
                            'sw_example_card',
                            'sw_example_card_title',
                        ],
                    },
                },
            });
        });

        it('names the parent of every block in the contract', () => {
            const files = card('{% block sw_example_card %}{% block sw_example_card_title %}{% endblock %}{% endblock %}');

            expect(files['module/sw-example/component/sw-example-card.d.ts']).toContain(
                [
                    '            blocks: {',
                    '                sw_example_card: Block<null>;',
                    "                sw_example_card_title: Block<'sw_example_card'>;",
                    '            };',
                ].join('\n'),
            );
        });

        it('prints a component without a template with no blocks', () => {
            const files = snapshot({ [`${CARD}/index.js`]: 'export default { render() {} };' });

            expect(files['module/sw-example/component/sw-example-card.d.ts']).toContain('blocks: {};');
            expect(files['module/sw-example/component/sw-example-card.api.md']).not.toContain('blocks');
        });

        it('names a component after its registration when the source is not an index file', () => {
            const files = snapshot({
                [REGISTRY]: "Shopware.Component.register('sw-example-card', () => import('./component/card'));",
                'src/module/sw-example/component/card.ts': 'export default {};',
            });

            expect(Object.keys(files)).toContain('module/sw-example/component/sw-example-card.d.ts');
            expect(files['module/sw-example/component/sw-example-card.d.ts']).toContain("lang: 'ts';");
        });
    });

    describe('extend and override', () => {
        const files = card('{% block sw_example_card %}{% endblock %}', {
            [REGISTRY]: [
                "Shopware.Component.register('sw-example-card', () => import('./component/sw-example-card'));",
                "Shopware.Component.extend('sw-example-tile', 'sw-example-card', () => import('./component/sw-example-tile'));",
                "Shopware.Component.override('sw-example-card', { template: overrideTemplate });",
            ].join('\n'),
            'src/module/sw-example/component/sw-example-tile/index.js':
                "import template from './sw-example-tile.html.twig';\n\nexport default { template };",
            'src/module/sw-example/component/sw-example-tile/sw-example-tile.html.twig':
                '{% block sw_example_card %}{% block sw_example_tile_badge %}{% endblock %}{% endblock %}',
        });

        it('prints an extend child with its parent and the blocks of its own template', () => {
            expect(files['module/sw-example/component/sw-example-tile.d.ts']).toContain(
                [
                    "kind: 'extend';",
                    "lang: 'js';",
                    "visibility: 'public';",
                    "visibilitySource: 'default';",
                    "extends: 'sw-example-card';",
                ]
                    .map((line) => `                ${line}`)
                    .join('\n'),
            );
            expect(files['module/sw-example/component/sw-example-tile.api.md']).toContain(
                'component sw-example-tile  public  js\n  extends sw-example-card\n',
            );
            expect(indexedComponents(files)['sw-example-tile']).toEqual({
                file: 'module/sw-example/component/sw-example-tile',
                visibility: 'public',
                extends: 'sw-example-card',
                blocks: [
                    'sw_example_card',
                    'sw_example_tile_badge',
                ],
            });
        });

        it('adds no component for a core override', () => {
            expect(Object.keys(indexedComponents(files))).toEqual([
                'sw-example-card',
                'sw-example-tile',
            ]);
        });
    });

    describe('visibility', () => {
        const template = '{% block sw_example_card %}{% endblock %}';

        it('marks every line of a component with a private export', () => {
            const files = card(template, {
                [`${CARD}/index.js`]:
                    "import template from './sw-example-card.html.twig';\n\n/**\n * @private\n */\nexport default { template };",
            });

            expect(files['module/sw-example/component/sw-example-card.api.md']).toContain(
                [
                    'component sw-example-card  private  js  [private]',
                    '  registered src/module/sw-example/index.js  [private]',
                    '  source src/module/sw-example/component/sw-example-card/index.js  [private]',
                    '  template src/module/sw-example/component/sw-example-card/sw-example-card.html.twig  [private]',
                    '  visibility-source export  [private]',
                    '',
                    'blocks',
                    '  sw_example_card  [private]',
                ].join('\n'),
            );
            expect(files['module/sw-example/component/sw-example-card.d.ts']).toContain(
                "        /** @private */\n        'sw-example-card': {",
            );
            expect(indexedComponents(files)['sw-example-card'].visibility).toBe('private');
        });

        it('reads a private tag directly above the registration', () => {
            const files = card(template, {
                [REGISTRY]:
                    "/** @private */\nShopware.Component.register('sw-example-card', () => import('./component/sw-example-card'));",
            });

            expect(files['module/sw-example/component/sw-example-card.d.ts']).toContain("visibilitySource: 'registration';");
        });

        it('ignores a private tag in the file header of the registrations', () => {
            const files = card(template, {
                [REGISTRY]: [
                    '/**\n * @private\n */',
                    'export default () => {',
                    '    /* eslint-disable sw-deprecation-rules/private-feature-declarations */',
                    "    Shopware.Component.register('sw-example-card', () => import('./component/sw-example-card'));",
                    '};',
                ].join('\n'),
            });

            expect(files['module/sw-example/component/sw-example-card.d.ts']).toContain("visibility: 'public';");
        });
    });

    describe('native setup SFCs', () => {
        const sfc = [
            '<template>',
            '    <sw-block name="sw_example_card">',
            '        <sw-block name="sw_example_card_title">Title</sw-block>',
            '        <sw-block :name="dynamicName">Dynamic</sw-block>',
            '        <sw-block-field name="title" />',
            '    </sw-block>',
            '</template>',
            '',
            '<script setup lang="ts"></script>',
        ].join('\n');

        it('reads the blocks of a .vue file behind a registration shim', () => {
            const files = snapshot({
                [`${CARD}/index.js`]: "/**\n * @private\n */\nexport { default } from './sw-example-card.vue';",
                [`${CARD}/sw-example-card.vue`]: sfc,
            });
            const contract = files['module/sw-example/component/sw-example-card.d.ts'];

            expect(contract).toContain(
                `// Source: ${CARD}/index.js\n// SFC: ${CARD}/sw-example-card.vue\n\ndeclare namespace AdminApi {`,
            );
            expect(contract).toContain("lang: 'vue';");
            expect(contract).toContain("visibilitySource: 'export';");
            expect(indexedComponents(files)['sw-example-card'].blocks).toEqual([
                'sw_example_card',
                'sw_example_card_title',
            ]);
        });

        it('takes a .vue file that the registration imports directly as the source', () => {
            const files = snapshot({
                [REGISTRY]:
                    "Shopware.Component.register('sw-example-card', () => import('./component/sw-example-card/sw-example-card.vue'));",
                [`${CARD}/sw-example-card.vue`]: sfc,
            });

            expect(files['module/sw-example/component/sw-example-card.api.md']).toContain(
                `component sw-example-card  public  vue\n  registered ${REGISTRY}\n  source ${CARD}/sw-example-card.vue\n\nblocks`,
            );
        });

        it('lists the block an override template extends', () => {
            const files = snapshot({
                [`${CARD}/index.js`]: "export { default } from './sw-example-card.vue';",
                [`${CARD}/sw-example-card.vue`]:
                    '<template><sw-block extends="sw_example_card">Replacement</sw-block></template>',
            });

            expect(indexedComponents(files)['sw-example-card'].blocks).toEqual(['sw_example_card']);
        });
    });

    describe('flag states', () => {
        it('marks blocks that exist in one flag state only', () => {
            expect(
                blockLines(`
                    {% block sw_example_card %}
                    <template v-if="feature.isActive('v6.8.0.0')">{% block sw_example_card_new %}{% endblock %}</template>
                    <template v-else>{% block sw_example_card_old %}{% endblock %}</template>
                    {% endblock %}
                `),
            ).toEqual([
                'sw_example_card',
                'sw_example_card_new  [from v6.8.0]',
                'sw_example_card_old  [until v6.8.0]',
            ]);
        });

        it('writes a major copy of the contract when the states differ', () => {
            const files = card(`
                {% block sw_example_card %}
                <div v-if="!feature.isActive('V6_8_0_0')">{% block sw_example_card_old %}{% endblock %}</div>
                {% endblock %}
            `);

            expect(files['module/sw-example/component/sw-example-card.d.ts']).toContain('sw_example_card_old');
            expect(files['module/sw-example/component/sw-example-card.major.d.ts']).not.toContain('sw_example_card_old');
            expect(files['module/sw-example/component/sw-example-card.major.d.ts']).toContain(
                '// Flag state: major (v6.8.0.0 and every flag with `major: v6.8.0.0` on).',
            );
            expect(files['module/sw-example/component/sw-example-card.api.md']).toContain(
                'Contract: `sw-example-card.d.ts` (current) and `sw-example-card.major.d.ts` (major).',
            );
        });

        it.each([
            ["feature.isActive('EXAMPLE_REWORK')", 'a flag that belongs to the major', '  [from v6.8.0]'],
            ["feature.isActive('EXAMPLE_REWORK') && isLoading", 'a major flag combined with other state', '  [from v6.8.0]'],
            ["!feature.isActive('v6.8.0.0') || isLoading", 'a condition that other state can still fulfil', ''],
            ["feature.isActive('OTHER_FLAG')", 'a flag that does not belong to the major', ''],
            ['isLoading', 'a condition without a feature check', ''],
        ])('reads %s as %s', (condition, _case, marker) => {
            expect(blockLines(`<div v-if="${condition}">{% block sw_example_card %}{% endblock %}</div>`)).toEqual([
                `sw_example_card${marker}`,
            ]);
        });

        it('follows a v-if chain through v-else-if', () => {
            expect(
                blockLines(`
                    <div v-if="isLoading">{% block sw_example_card_loader %}{% endblock %}</div>
                    <div v-else-if="feature.isActive('v6.8.0.0')">{% block sw_example_card_new %}{% endblock %}</div>
                    <div v-else-if="isEmpty">{% block sw_example_card_empty %}{% endblock %}</div>
                    <div v-else>{% block sw_example_card_old %}{% endblock %}</div>
                `),
            ).toEqual([
                'sw_example_card_empty  [until v6.8.0]',
                'sw_example_card_loader',
                'sw_example_card_new  [from v6.8.0]',
                'sw_example_card_old  [until v6.8.0]',
            ]);
        });

        it('lists a block once when both branches declare it', () => {
            expect(
                blockLines(`
                    <div v-if="feature.isActive('v6.8.0.0')">{% block sw_example_card %}{% endblock %}</div>
                    <div v-else>{% block sw_example_card %}{% endblock %}</div>
                `),
            ).toEqual(['sw_example_card']);
        });

        it('lists a block under the parent it has in each state', () => {
            expect(
                blockLines(`
                    {% block sw_example_card %}
                    <template v-if="feature.isActive('v6.8.0.0')">{% block sw_example_card_body %}</template>
                    {% block sw_example_card_title %}{% endblock %}
                    <template v-if="feature.isActive('v6.8.0.0')">{% endblock %}</template>
                    {% endblock %}
                `),
            ).toEqual([
                'sw_example_card',
                'sw_example_card_body  [from v6.8.0]',
                'sw_example_card_title  [from v6.8.0]',
                'sw_example_card_title  [until v6.8.0]',
            ]);
        });

        it('limits a component that is registered behind a feature check to that state', () => {
            const files = card('{% block sw_example_card %}{% endblock %}', {
                [REGISTRY]: [
                    "if (!Shopware.Feature.isActive('v6.8.0.0')) {",
                    "    Shopware.Component.register('sw-example-card', () => import('./component/sw-example-card'));",
                    '}',
                ].join('\n'),
            });

            expect(files['module/sw-example/component/sw-example-card.api.md']).toContain(
                '  sw_example_card  [until v6.8.0]',
            );
        });
    });

    describe('templates', () => {
        it('lists a block under every parent it has, and survives a block inside itself', () => {
            const files = card(`
                {% block sw_example_card %}
                    {% block sw_example_card %}{% block sw_example_card_title %}{% endblock %}{% endblock %}
                    {% block sw_example_card_title %}{% endblock %}
                {% endblock %}
            `);

            expect(files['module/sw-example/component/sw-example-card.d.ts']).toContain(
                [
                    "sw_example_card: Block<null | 'sw_example_card'>;",
                    "sw_example_card_title: Block<'sw_example_card'>;",
                ]
                    .map((line) => `                ${line}`)
                    .join('\n'),
            );
        });

        it('ignores a block tag inside a Twig comment', () => {
            expect(blockLines('{# {% block sw_old %}{% endblock %} #}{% block sw_example_card %}{% endblock %}')).toEqual([
                'sw_example_card',
            ]);
        });

        it('reads <sw-block> elements and Twig blocks of one template into one tree', () => {
            expect(
                blockLines(
                    '{% block sw_example_card %}<sw-block name="sw_example_card_title">Title</sw-block>{% endblock %}',
                ),
            ).toEqual([
                'sw_example_card',
                'sw_example_card_title',
            ]);
        });
    });

    describe('sources it cannot read', () => {
        it.each([
            [
                'a block that is never closed',
                '{% block sw_example_card %}',
                'sw-example-card.html.twig: the block sw_example_card is never closed.',
            ],
            [
                'an endblock without a block',
                '{% endblock %}',
                'sw-example-card.html.twig: an {% endblock %} closes a block that was never opened.',
            ],
            [
                'a block tag inside an HTML tag',
                '<div {% block sw_example_card %}{% endblock %}></div>',
                'sw-example-card.html.twig: a {% block %} tag sits where the HTML parser cannot see it',
            ],
        ])('rejects %s', (_case, template, message) => {
            expect(() => card(template)).toThrow(message);
        });

        it('rejects blocks in a template that no component uses', () => {
            expect(() =>
                card('{% block sw_example_card %}{% endblock %}', {
                    'src/module/sw-example/component/sw-example-unused/sw-example-unused.html.twig':
                        '{% block sw_example_unused %}{% endblock %}',
                }),
            ).toThrow('sw_example_unused in src/module/sw-example/component/sw-example-unused/sw-example-unused.html.twig');
        });

        it.each([
            [
                'a component name that is not a literal',
                'Shopware.Component.register(name, () => import("./component/sw-example-card"));',
                'Component.register needs a literal component name',
            ],
            [
                'a component that is neither an object literal nor a lazy import',
                "Shopware.Component.register('sw-example-card', config);",
                "Component.register('sw-example-card') has to pass an object literal or () => import('...').",
            ],
            [
                'an import that matches no file',
                "Shopware.Component.register('sw-example-card', () => import('./component/sw-missing'));",
                "the import './component/sw-missing' matches no source file.",
            ],
            [
                'two components that share one snapshot file',
                [
                    "Shopware.Component.register('sw-example-card', () => import('./component/sw-example-card'));",
                    "Shopware.Component.register('sw-example-card', () => import('./component/sw-example-card'));",
                ].join('\n'),
                'The components sw-example-card and sw-example-card would both be written to',
            ],
        ])('rejects %s', (_case, registry, message) => {
            expect(() => card('', { [REGISTRY]: registry })).toThrow(message);
        });
    });
});
