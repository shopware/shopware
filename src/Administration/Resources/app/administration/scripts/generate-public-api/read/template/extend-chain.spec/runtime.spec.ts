/**
 * @sw-package framework
 *
 * The generator merges the blocks along an extend chain itself, without running the Administration. These
 * tests register the same templates with the factory the Administration uses at runtime and compare the two
 * results, the origin of every block included, so the generator cannot drift from the rules of `template.factory.js`.
 */
import type { TemplateBlock } from '../../../model/blocks';
import { BOTH_STATES, createFlagContext, inBothStates } from '../../../model/flag-states';
import type { FlagContext, FlagStates } from '../../../model/flag-states';
import { ADMINISTRATION_ROOT, currentMajorFlags } from '../../../source/checkout';
import { readSourceTree } from '../../../source/source-tree';
import { buildModel } from '../../build-model';
import { componentCalls } from '../../component-registry';
import { listBlocks } from '../block-tree';
import { resolveTemplates } from '../extend-chain';
import { templateTree } from '../template-blocks';
import { blockLines, fileOf, runtimeBlocks } from './runtime-blocks';
import type { Component } from './runtime-blocks';

const MAJOR = "feature.isActive('v6.8.0.0')";

const FLAGS = createFlagContext(['v6.8.0.0']);

function generatorBlocks(components: Component[], flags: FlagContext): Map<string, TemplateBlock[]> {
    const source = (name: string, template: string) => ({
        file: fileOf(name),
        tree: templateTree(template, fileOf(name), flags),
    });
    const resolved = resolveTemplates(
        components.map(({ name, extends: parent = null, template, overrides = [] }) => ({
            name,
            extends: parent,
            native: false,
            states: BOTH_STATES,
            template: template === null || template === undefined ? null : source(name, template),
            overrides: overrides.map(({ index, template: text }) => ({ index, template: source(name, text) })),
        })),
    );

    return new Map(components.map(({ name }, index) => [name, listBlocks(resolved[index].tree)]));
}

function linesByComponent(blocks: Map<string, TemplateBlock[]>): Record<string, string[]> {
    return Object.fromEntries([...blocks].map(([name, componentBlocks]) => [name, blockLines(componentBlocks)]));
}

const BASE = [
    '{% block base %}',
    '<div>',
    '    {% block base_header %}<h1>{% block base_title %}Title{% endblock %}</h1>{% endblock %}',
    '    {% block base_body %}Body{% endblock %}',
    '</div>',
    '{% endblock %}',
].join('\n');

const base = (overrides: Component['overrides'] = []): Component => ({ name: 'sw-base', template: BASE, overrides });

const child = (template: string | null, parent = 'sw-base', name = 'sw-child'): Component => ({
    name,
    extends: parent,
    template,
});

const CASES: [string, Component[]][] = [
    ['a child without a template', [base(), child(null)]],
    ['a child that replaces a block', [base(), child('{% block base_header %}<h2>Child</h2>{% endblock %}')]],
    [
        'a child that keeps the parent content',
        [base(), child('{% block base_header %}<header>{% parent %}</header>{% endblock %}')],
    ],
    [
        'a child that keeps the parent content twice',
        [base(), child('{% block base_header %}{% parent %}<hr>{% parent %}{% endblock %}')],
    ],
    [
        '{% parent %} inside a block of the redeclaration',
        [base(), child('{% block base_header %}{% block child_wrap %}{% parent %}{% endblock %}{% endblock %}')],
    ],
    [
        '{% parent %} two blocks deep, which the runtime leaves unresolved',
        [
            base(),
            child(
                '{% block base_header %}{% block child_a %}{% block child_b %}{% parent %}{% endblock %}{% endblock %}{% endblock %}',
            ),
        ],
    ],
    [
        'a nested block with the name of another parent block',
        [base(), child('{% block base_header %}{% block base_body %}Moved{% endblock %}{% endblock %}')],
    ],
    [
        'a wrapper block that the parent does not have',
        [base(), child('{% block child_root %}text{% block base_body %}Child{% endblock %}{% endblock %}')],
    ],
    [
        'two wrapper blocks, of which the runtime unwraps one',
        [
            base(),
            child(
                '{% block child_outer %}{% block child_inner %}{% block base_body %}Child{% endblock %}{% endblock %}{% endblock %}',
            ),
        ],
    ],
    [
        'a block below a block the child replaces',
        [base(), child('{% block base_header %}Child{% endblock %}{% block base_title %}Dropped{% endblock %}')],
    ],
    [
        'a block below a block the child redeclares with {% parent %}',
        [base(), child('{% block base_header %}{% parent %}{% endblock %}{% block base_title %}Dropped{% endblock %}')],
    ],
    [
        'the outermost block redeclared with a nested parent block',
        [base(), child('{% block base %}{% block base_body %}Only{% endblock %}{% endblock %}')],
    ],
    [
        'a block the child declares twice',
        [
            base(),
            child(
                '{% block base_body %}First{% block child_first %}{% endblock %}{% endblock %}{% block base_body %}Second{% endblock %}',
            ),
        ],
    ],
    ['text and unknown blocks outside every parent block', [base(), child('before{% block nowhere %}x{% endblock %}after')]],
    [
        'a block the parent has several times',
        [
            {
                name: 'sw-base',
                template:
                    '{% block base %}{% block item %}1{% block item_first %}{% endblock %}{% endblock %}<hr>' +
                    '{% block item %}2{% block item_second %}{% endblock %}{% endblock %}{% endblock %}',
            },
            child('{% block item %}[{% parent %}]{% endblock %}'),
        ],
    ],
    [
        'a block the parent has under two parents',
        [
            {
                name: 'sw-base',
                template:
                    '{% block left %}{% block item %}1{% block item_inner %}{% endblock %}{% endblock %}{% endblock %}' +
                    '{% block right %}{% block item %}2{% endblock %}{% endblock %}',
            },
            child('{% block item %}{% parent %}{% endblock %}'),
        ],
    ],
    [
        'a grandchild that targets blocks of both ancestors',
        [
            base(),
            child('{% block base_header %}{% parent %}{% block child_extra %}Extra{% endblock %}{% endblock %}'),
            child(
                '{% block child_extra %}Grandchild{% endblock %}{% block base_title %}Grandchild{% endblock %}',
                'sw-child',
                'sw-grandchild',
            ),
        ],
    ],
    [
        'a grandchild below a child without a template',
        [base(), child(null), child('{% block base_title %}Grandchild{% endblock %}', 'sw-child', 'sw-grandchild')],
    ],
    [
        'a wrapper the parent replaced away',
        [
            base(),
            child('{% block base_header %}Child{% endblock %}'),
            child(
                '{% block base_title %}{% block base_body %}Grandchild{% endblock %}{% endblock %}',
                'sw-child',
                'sw-grandchild',
            ),
        ],
    ],
    [
        'a core override of the parent that adds a block',
        [
            base([
                {
                    index: 0,
                    template: '{% block base_header %}{% parent %}{% block override_extra %}{% endblock %}{% endblock %}',
                },
            ]),
            child('{% block override_extra %}Child{% endblock %}'),
        ],
    ],
    [
        'a core override of the grandparent that replaces a block',
        [
            base([{ index: 0, template: '{% block base_header %}{% block override_extra %}{% endblock %}{% endblock %}' }]),
            child('{% block base_body %}Child{% endblock %}'),
            child('{% block override_extra %}Grandchild{% endblock %}', 'sw-child', 'sw-grandchild'),
        ],
    ],
    [
        'a core override of the child',
        [
            base(),
            {
                ...child('{% block base_header %}Child{% block child_extra %}{% endblock %}{% endblock %}'),
                overrides: [
                    {
                        index: 0,
                        template: '{% block child_extra %}Override{% endblock %}{% block base_body %}Override{% endblock %}',
                    },
                ],
            },
        ],
    ],
    [
        'an override with text, an unknown wrapper and a block below one it replaces',
        [
            base([
                {
                    index: 0,
                    template:
                        'text{% block unknown %}{% block base_body %}Dropped{% endblock %}{% endblock %}' +
                        '{% block base_header %}Override{% endblock %}{% block base_title %}Dropped{% endblock %}',
                },
            ]),
        ],
    ],
    [
        'an override that redeclares a block with {% parent %} and then a block below it',
        [
            base([
                {
                    index: 0,
                    template:
                        '{% block base_header %}{% parent %}{% endblock %}{% block base_title %}Override{% endblock %}',
                },
            ]),
        ],
    ],
    [
        'two overrides in the order of their index',
        [
            base([
                {
                    index: 1,
                    template: '{% block override_first %}{% block override_second %}{% endblock %}{% endblock %}',
                },
                { index: 0, template: '{% block base_header %}First{% block override_first %}{% endblock %}{% endblock %}' },
            ]),
        ],
    ],
    [
        'a parent block that exists in one flag state, as in #17339',
        [
            {
                name: 'sw-base',
                template: [
                    '{% block base %}',
                    `<template v-if="${MAJOR}">{% block base_tabs_new %}New{% endblock %}</template>`,
                    '<template v-else>{% block base_tabs %}Old{% block base_tabs_item %}{% endblock %}{% endblock %}</template>',
                    '{% endblock %}',
                ].join('\n'),
            },
            child('{% block base_tabs %}{% parent %}{% block child_checkbox %}{% endblock %}{% endblock %}'),
        ],
    ],
    [
        'a block both branches of a flag declare, where {% parent %} takes the first one',
        [
            {
                name: 'sw-base',
                template: [
                    '{% block base %}',
                    `<template v-if="${MAJOR}">{% block base_tabs %}New{% block base_tabs_new %}{% endblock %}{% endblock %}</template>`,
                    '<template v-else>{% block base_tabs %}Old{% block base_tabs_old %}{% endblock %}{% endblock %}</template>',
                    '{% endblock %}',
                ].join('\n'),
            },
            child('{% block base_tabs %}{% parent %}{% endblock %}'),
        ],
    ],
    [
        'child content behind a flag',
        [
            base(),
            child(
                [
                    '{% block base_header %}',
                    `<div v-if="!${MAJOR}">{% parent %}</div>`,
                    `<div v-else>{% block child_new %}{% endblock %}</div>`,
                    '{% endblock %}',
                    `<template v-if="${MAJOR}">{% block base_body %}{% block child_body %}{% endblock %}{% endblock %}</template>`,
                ].join('\n'),
            ),
        ],
    ],
];

describe('scripts/generate-public-api/read/template/extend-chain against the runtime', () => {
    describe.each(CASES)('%s', (_case, components) => {
        it.each([
            ['parents first', components],
            ['children first', [...components].reverse()],
        ])('gives the blocks the runtime builds when it registers %s', (_order, ordered) => {
            const generated = linesByComponent(generatorBlocks(ordered, FLAGS));
            const runtime = linesByComponent(runtimeBlocks(ordered, FLAGS));

            expect(Object.keys(runtime).sort()).toEqual(components.map(({ name }) => name).sort());
            expect(generated).toEqual(runtime);
        });
    });

    describe('the Administration', () => {
        const tree = readSourceTree(ADMINISTRATION_ROOT);
        const options = { majorFlags: currentMajorFlags() };
        const model = buildModel(tree, options);
        const flags = createFlagContext(options.majorFlags);
        const { registrations, overrides } = componentCalls(tree, flags);
        const runtime = runtimeBlocks(
            registrations.map(({ name, extends: parent, template }) => ({
                name,
                extends: parent,
                template: template === null ? null : tree.read(template),
                overrides: overrides
                    .filter((override) => override.target === name && override.template !== null)
                    .map(({ index, template: file }) => ({ index, template: tree.read(file ?? '') })),
            })),
            flags,
        );
        const twigComponents = model.components.filter(({ name }) => runtime.has(name));
        const byName = new Map(model.components.map((unit) => [unit.name, unit]));

        /** The runtime knows no flag states, so its blocks count where the whole extend chain is registered. */
        function registeredIn(name: string): FlagStates {
            const unit = byName.get(name);
            const parent = unit?.extends ? registeredIn(unit.extends) : BOTH_STATES;

            return unit ? inBothStates(unit.states, parent) : parent;
        }

        function runtimeLines(name: string): string[] {
            const states = registeredIn(name);

            return blockLines(
                (runtime.get(name) ?? []).map((block) => ({ ...block, states: inBothStates(block.states, states) })),
            );
        }

        it('covers every extend child and every component with a Twig template', () => {
            const missing = model.components
                .filter(
                    ({ name, extends: parent, template }) => (parent !== null || template !== null) && !runtime.has(name),
                )
                .map(({ name }) => name);

            expect(missing).toEqual([]);
            expect(twigComponents.filter((unit) => unit.extends !== null).length).toBeGreaterThan(90);
        });

        it('lists for every component the blocks the runtime builds', () => {
            const different = twigComponents
                .map((unit) => ({ name: unit.name, generated: blockLines(unit.blocks), runtime: runtimeLines(unit.name) }))
                .filter((unit) => JSON.stringify(unit.generated) !== JSON.stringify(unit.runtime));

            expect(different).toEqual([]);
        });

        /*
         * The runtime drops such a block without a warning, so it is in no component although a template
         * declares it. Fix the template: the block never renders.
         */
        it('drops no block of an extend or override template', () => {
            expect(model.droppedBlocks.map(({ kind, block, file }) => `${kind}  ${block}  ${file}`)).toEqual([]);
        });
    });
});
