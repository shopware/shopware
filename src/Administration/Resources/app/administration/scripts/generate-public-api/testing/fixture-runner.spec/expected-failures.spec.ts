/**
 * @sw-package framework
 *
 * How the runner marks the comparisons on the expected-fail list, and which lists and unit kinds it rejects.
 */
import { fixtureTests } from '../fixture-runner';
import type { FixtureCase } from '../fixture-runner';
import {
    CROSSING_FINDING,
    EXPECTED,
    FILES,
    FINDINGS,
    FIXTURE,
    NO_FAILURES,
    SERVICE_FINDING,
    comparison,
    inBothSnapshots,
    withFile,
} from './example';

describe('fixtureTests expected failures', () => {
    it('marks every comparison of a view on the expected-fail list', () => {
        const expectedFailures = { views: ['block-tree'], comparisons: [] };

        expect(comparison('example/block-tree', EXPECTED, expectedFailures).expectedToFail).toBe(true);
        expect(comparison('example/block-structure', EXPECTED, expectedFailures).expectedToFail).toBe(false);
        expect(comparison('example/complete', EXPECTED, expectedFailures).expectedToFail).toBe(false);
    });

    it('leaves a comparison of a listed view unmarked when the case expects nothing of that view', () => {
        const withoutService: FixtureCase = {
            ...FIXTURE,
            name: 'no-service',
            expected: { before: {}, after: {}, findings: [] },
        };
        const marked = fixtureTests(
            [FIXTURE, withoutService],
            { views: ['service-units'], comparisons: [] },
            { generateSnapshot: () => ({ files: {} }), classifyChanges: () => [] },
        )
            .filter(({ view }) => view === 'service-units')
            .map(({ name, expectedToFail }) => [name, expectedToFail]);

        expect(marked).toEqual([
            ['example/service-units', true],
            ['no-service/service-units', false],
        ]);
    });

    it('marks a single comparison on the expected-fail list', () => {
        const expectedFailures = { views: [], comparisons: ['example/complete'] };

        expect(comparison('example/block-tree', EXPECTED, expectedFailures).expectedToFail).toBe(false);
        expect(comparison('example/complete', EXPECTED, expectedFailures).expectedToFail).toBe(true);
    });

    it('keeps an expected failure marked once its output matches, which it.failing reports as a failure', () => {
        const { expectedToFail, actual, expected } = comparison('example/block-tree', EXPECTED, {
            views: [],
            comparisons: ['example/block-tree'],
        });

        expect(expectedToFail).toBe(true);
        expect(actual()).toEqual(expected);
    });

    it('runs the generator and the break check once per case', () => {
        const generateSnapshot = jest.fn(() => ({ files: FILES }));
        const classifyChanges = jest.fn(() => FINDINGS);

        fixtureTests([FIXTURE], NO_FAILURES, { generateSnapshot, classifyChanges }).forEach(({ actual, expected }) => {
            expect(actual()).toEqual(expected);
        });

        expect(generateSnapshot.mock.calls).toEqual([
            [FIXTURE.beforeRoot, { majorFlags: ['v6.8.0.0'] }],
            [FIXTURE.afterRoot, { majorFlags: ['v6.8.0.0'] }],
        ]);
        expect(classifyChanges).toHaveBeenCalledTimes(1);
        expect(classifyChanges).toHaveBeenCalledWith({ files: FILES }, { files: FILES });
    });

    it.each([
        [
            'report section blocks for the unit kind block-tree',
            inBothSnapshots(withFile('app/component/base/sw-example-card.api.md', 'blocks\n', 'slots\n')),
        ],
        [
            'index key blocks for the unit kind block-tree',
            inBothSnapshots(withFile('index.json', '"blocks"', '"slots"')),
        ],
        [
            'contract member blocks for the unit kind block-contract',
            inBothSnapshots(withFile('app/component/base/sw-example-card.d.ts', 'blocks: {', 'slots: {')),
        ],
        [
            'report section block-context for the unit kind block-structure',
            inBothSnapshots(withFile('app/component/base/sw-example-card.api.md', '  [scope: item]', '')),
        ],
        [
            'contract member blockContext for the unit kind block-contract',
            inBothSnapshots(withFile('app/component/base/sw-example-card.d.ts', ", 'scope:item'", '')),
        ],
        [
            'contract member crossings for the unit kind block-contract',
            inBothSnapshots(withFile('app/component/base/sw-example-card.d.ts', 'crossings:', 'refs:')),
        ],
        [
            'findings on crossing elements for the unit kind block-findings',
            { ...EXPECTED, findings: FINDINGS.filter((finding) => finding !== CROSSING_FINDING) },
        ],
        [
            'findings on service elements for the unit kind service-units',
            { ...EXPECTED, findings: FINDINGS.filter((finding) => finding !== SERVICE_FINDING) },
        ],
        [
            'report section service for the unit kind service-units',
            inBothSnapshots(withFile('app/service/example.service.api.md', 'service exampleService', 'store example')),
        ],
    ])('rejects a part that occurs in no expected output, which would compare nothing: %s', (part, expected) => {
        expect(() =>
            fixtureTests([{ ...FIXTURE, expected }], NO_FAILURES, {
                generateSnapshot: () => ({ files: {} }),
                classifyChanges: () => [],
            }),
        ).toThrow(`No fixture case has expected ${part}.`);
    });

    it.each([
        [
            'an unknown view',
            { views: ['removed-kind'], comparisons: [] },
            'The expected-fail list names views that do not exist: removed-kind.',
        ],
        [
            'a comparison of a removed case',
            { views: [], comparisons: ['removed-case/block-tree'] },
            'The expected-fail list names comparisons that do not exist: removed-case/block-tree.',
        ],
        [
            'a comparison of an unknown view',
            { views: [], comparisons: ['example/blocks'] },
            'The expected-fail list names comparisons that do not exist: example/blocks.',
        ],
        [
            'a comparison whose view is listed',
            { views: ['block-tree'], comparisons: ['example/block-tree'] },
            'The expected-fail list names comparisons whose view it lists already: example/block-tree.',
        ],
    ])('rejects %s on the expected-fail list', (_, expectedFailures, message) => {
        expect(() => comparison('example/block-tree', EXPECTED, expectedFailures)).toThrow(message);
    });
});
