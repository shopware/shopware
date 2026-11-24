/**
 * @sw-package framework
 *
 * Which views of a case fail for a difference between the expected and the actual output.
 */
import { fixtureTests } from '../fixture-runner';
import {
    BLOCK_FINDING,
    EXPECTED,
    FINDINGS,
    FIXTURE,
    MEMBER_FINDINGS,
    NO_FAILURES,
    SERVICE_FINDING,
    SERVICE_METHOD_FINDING,
    VIEWS,
    failingViews,
    withBlockFinding,
    withFile,
    withFinding,
} from './example';

describe('fixtureTests views', () => {
    it('compares every case per unit kind and as a whole', () => {
        const names = fixtureTests([FIXTURE], NO_FAILURES, {
            generateSnapshot: () => ({ files: {} }),
            classifyChanges: () => [],
        }).map((test) => test.name);

        expect(names).toEqual(VIEWS.map((view) => `example/${view}`));
    });

    it('passes every unit kind and the complete output when they match', () => {
        expect(failingViews(EXPECTED)).toEqual([]);
    });

    it.each([
        [
            'the nesting in the report',
            withFile('app/component/base/sw-example-card.api.md', '    sw_example_card_title', '  sw_example_card_title'),
            ['block-tree', 'complete'],
        ],
        [
            'a flag state marker in the report',
            withFile('app/component/base/sw-example-card.api.md', '[until v6.8.0]', '[from v6.8.0]'),
            ['block-tree', 'complete'],
        ],
        [
            'the origin of a block in the report',
            withFile('app/component/base/sw-example-card.api.md', '  sw_example_card\n', '  sw_example_card  [inherited]\n'),
            ['block-tree', 'complete'],
        ],
        [
            'the parent in the contract',
            withFile('app/component/base/sw-example-card.d.ts', "Block<'sw_example_card', ", 'Block<null, '),
            ['block-contract', 'complete'],
        ],
        [
            'the origin of a block in the contract',
            withFile(
                'app/component/base/sw-example-card.d.ts',
                'sw_example_card: Block<null>;',
                "sw_example_card: Block<null, 'inherited'>;",
            ),
            ['block-contract', 'complete'],
        ],
        [
            'the index',
            withFile('index.json', '"sw_example_card_title"', '"sw_example_card_headline"'),
            ['block-tree', 'complete'],
        ],
        [
            'a scope marker in the report',
            withFile('app/component/base/sw-example-card.api.md', '[scope: item]', '[after v-if]'),
            ['block-structure', 'complete'],
        ],
        [
            'another kind of block context in the report',
            withFile('app/component/base/sw-example-card.api.md', '[scope: item]', '[scope: item]  [slot: default]'),
            ['block-structure', 'complete'],
        ],
        [
            'a scope in the contract',
            withFile('app/component/base/sw-example-card.d.ts', "'scope:item'", "'afterIf'"),
            ['block-contract', 'complete'],
        ],
        [
            'a crossing in the report',
            withFile('app/component/base/sw-example-card.api.md', 'div  sw_example_card', 'span  sw_example_card'),
            ['block-structure', 'complete'],
        ],
        [
            'a crossing in the contract',
            withFile('app/component/base/sw-example-card.d.ts', "'div'", "'span'"),
            ['block-contract', 'complete'],
        ],
        ['the rule of a finding', withBlockFinding({ rule: 'block_missing' }), ['block-findings', 'complete']],
        ['the group of a finding', withBlockFinding({ group: 'Info' }), ['block-findings', 'complete']],
        [
            'the details of a finding',
            withBlockFinding({ at: 'sw-example-card.html.twig:9' }),
            ['block-findings', 'complete'],
        ],
        [
            'a finding on a block scope',
            {
                ...EXPECTED,
                findings: [
                    ...FINDINGS,
                    { ...BLOCK_FINDING, element: `${BLOCK_FINDING.element}/scope:item`, rule: 'block_scope_missing' },
                ],
            },
            ['block-findings', 'complete'],
        ],
        [
            'a missing snapshot',
            { ...EXPECTED, before: {} },
            [
                'block-tree',
                'block-contract',
                'block-structure',
                'component-members',
                'member-contract',
                'service-units',
                'service-members',
                'complete',
            ],
        ],
        [
            'the props in the report',
            withFile('app/component/base/sw-example-card.api.md', 'title?: string', 'title: string'),
            ['component-members', 'complete'],
        ],
        [
            'a template ref in the report',
            withFile('app/component/base/sw-example-card.api.md', 'title  -> h3', 'title  -> h4'),
            ['component-members', 'complete'],
        ],
        [
            'the emits in the contract',
            withFile('app/component/base/sw-example-card.d.ts', 'emits: {};', 'emits: { change: [] };'),
            ['member-contract', 'complete'],
        ],
        [
            'the header of the report',
            withFile('app/component/base/sw-example-card.api.md', 'public  js', 'private  js'),
            ['complete'],
        ],
        ['the visibility in the index', withFile('index.json', '"public"', '"private"'), ['complete']],
        [
            'a finding on a method parameter',
            withFinding(MEMBER_FINDINGS[2], { element: `${MEMBER_FINDINGS[2].element}/param:event` }),
            ['member-findings', 'complete'],
        ],
        ['an entry point', withFile('current.d.ts', 'sw-example-card.d.ts', 'sw-example-panel.d.ts'), ['complete']],
        [
            'the header of a service in the report',
            withFile('app/service/example.service.api.md', 'typed  provider', 'any  provider'),
            ['service-units', 'complete'],
        ],
        [
            'a method of a service in the report',
            withFile('app/service/example.service.api.md', 'load(id: string)', 'load()'),
            ['service-members', 'complete'],
        ],
        [
            'a service in the index',
            withFile('index.json', '"app/service/example.service"', '"app/service/other.service"'),
            ['service-units', 'complete'],
        ],
        ['a finding on a service', withFinding(SERVICE_FINDING, { group: 'Private' }), ['service-units', 'complete']],
        [
            'a finding on a method of a service',
            withFinding(SERVICE_METHOD_FINDING, { group: 'Private' }),
            ['service-members', 'complete'],
        ],
    ])('fails only the views that cover a difference in %s', (_, output, expectedFailingViews) => {
        expect(failingViews(output)).toEqual(expectedFailingViews);
    });
});
