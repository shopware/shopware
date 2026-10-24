/**
 * @sw-package framework
 */

import { createDiff } from '../create-diff';
import type { ApiChange } from '../types';
import {
    block,
    component,
    diffComponent,
    diffComponents,
    diffObject,
    method,
    named,
    node,
    param,
    prop,
    root,
    snapshot,
} from './builders';

const deprecated = { version: 'v6.8.0', message: '' };

function ratings(changes: ApiChange[]): Array<Pick<ApiChange, 'member' | 'severity' | 'impact' | 'privateVisibility'>> {
    return changes.map(({ member, severity, impact, privateVisibility }) => ({
        member,
        severity,
        impact,
        privateVisibility,
    }));
}

describe('createDiff: impact of private changes', () => {
    describe('removed', () => {
        it('rates a removal without deprecation as breaking', () => {
            const changes = diffComponents([component('sw-old', { visibility: 'internal' })], []);

            expect(ratings(changes)).toEqual([
                { member: 'sw-old', severity: 'private', impact: 'breaking', privateVisibility: 'internal' },
            ]);
            expect(changes[0].reasons).toEqual(['Removed; it was @internal.', 'Removed without prior deprecation.']);
        });

        it('rates the removal of a deprecated entry as removedDeprecated', () => {
            const changes = diffComponent(
                { methods: [method('load', [], { visibility: 'experimental', deprecation: deprecated })] },
                {},
            );

            expect(ratings(changes)).toEqual([
                { member: 'load', severity: 'private', impact: 'removedDeprecated', privateVisibility: 'experimental' },
            ]);
        });

        it('rates the removal of a member of a deprecated private component as removedDeprecated', () => {
            const changes = diffComponent(
                { visibility: 'private', deprecation: deprecated, props: [prop('value')], blocks: [block('sw_foo')] },
                { visibility: 'private', deprecation: deprecated },
            );

            expect(ratings(changes)).toEqual([
                { member: 'sw_foo', severity: 'private', impact: 'removedDeprecated', privateVisibility: 'private' },
                { member: 'value', severity: 'private', impact: 'removedDeprecated', privateVisibility: 'private' },
            ]);
            changes.forEach((change) => {
                expect(change.reasons).toEqual([
                    'Removed; it was @private.',
                    'Removed; component `sw-foo` is deprecated since v6.8.0.',
                ]);
            });
        });

        it('rates the removal of a private node below a deprecated ancestor as removedDeprecated', () => {
            const state = (children: ReturnType<typeof node>[]): ReturnType<typeof node> =>
                node('Shopware.State', { kind: 'object', signature: 'object', deprecation: deprecated, children });

            const changes = diffObject([state([node('Shopware.State.get', { visibility: 'internal' })])], [state([])]);

            expect(ratings(changes)).toEqual([
                {
                    member: 'Shopware.State.get',
                    severity: 'private',
                    impact: 'removedDeprecated',
                    privateVisibility: 'internal',
                },
            ]);
        });
    });

    describe('added', () => {
        it('rates a new required prop on a private component as breaking', () => {
            const changes = diffComponent(
                { visibility: 'private' },
                { visibility: 'private', props: [prop('p', { required: true })] },
            );

            expect(ratings(changes)).toEqual([
                { member: 'p', severity: 'private', impact: 'breaking', privateVisibility: 'private' },
            ]);
            expect(changes[0].reasons).toEqual(['Added as @private.', 'New required prop.']);
        });

        it('rates other additions as added and uses the head visibility', () => {
            const changes = diffComponent({}, { emits: [named('change', { visibility: 'experimental' })] });

            expect(ratings(changes)).toEqual([
                { member: 'change', severity: 'private', impact: 'added', privateVisibility: 'experimental' },
            ]);
        });

        it("uses the owner's visibility when the owner is not public", () => {
            const changes = diffComponent(
                { visibility: 'internal' },
                { visibility: 'internal', slots: [named('footer', { visibility: 'experimental' })] },
            );

            expect(ratings(changes)).toEqual([
                { member: 'footer', severity: 'private', impact: 'added', privateVisibility: 'internal' },
            ]);
        });

        it('rates an added private component as added', () => {
            const changes = diffComponents([], [component('sw-new', { visibility: 'internal' })]);

            expect(ratings(changes)).toEqual([
                { member: 'sw-new', severity: 'private', impact: 'added', privateVisibility: 'internal' },
            ]);
        });
    });

    describe('changed', () => {
        it('rates a change by its strongest finding and uses the base visibility', () => {
            const changes = diffComponent(
                { methods: [method('load', [param('id')], { visibility: 'internal' })] },
                { methods: [method('load', [param('id', { type: 'number' })], { visibility: 'private' })] },
            );

            expect(ratings(changes)).toEqual([
                { member: 'load', severity: 'private', impact: 'potentiallyBreaking', privateVisibility: 'internal' },
            ]);
        });

        it('rates a breaking change of a member of a private component as breaking', () => {
            const changes = diffComponent(
                { visibility: 'experimental', methods: [method('load', [param('id')])] },
                { visibility: 'experimental', methods: [method('load', [])] },
            );

            expect(ratings(changes)).toEqual([
                { member: 'load', severity: 'private', impact: 'breaking', privateVisibility: 'experimental' },
            ]);
        });

        it('rates a newly deprecated private entry as newlyDeprecated', () => {
            const changes = diffComponent({ visibility: 'internal' }, { visibility: 'internal', deprecation: deprecated });

            expect(ratings(changes)).toEqual([
                { member: 'sw-foo', severity: 'private', impact: 'newlyDeprecated', privateVisibility: 'internal' },
            ]);
        });
    });

    it('leaves impact and privateVisibility empty for public changes and visibility transitions', () => {
        const changes = diffComponents(
            [component('sw-a', { props: [prop('value')] }), component('sw-b'), component('sw-c', { visibility: 'private' })],
            [
                component('sw-a'),
                component('sw-b', { visibility: 'internal' }),
                component('sw-c'),
                component('sw-d'),
            ],
        );

        expect(ratings(changes)).toEqual([
            { member: 'value', severity: 'breaking', impact: null, privateVisibility: null },
            { member: 'sw-b', severity: 'potentiallyBreaking', impact: null, privateVisibility: null },
            { member: 'sw-c', severity: 'compatible', impact: null, privateVisibility: null },
            { member: 'sw-d', severity: 'added', impact: null, privateVisibility: null },
        ]);
    });

    it('counts private changes by impact in the summary', () => {
        const diff = createDiff(
            snapshot({
                components: [
                    component('sw-a', { visibility: 'internal', props: [prop('a')], methods: [method('m', [param('x')])] }),
                    component('sw-b', { visibility: 'private', deprecation: deprecated }),
                    component('sw-public', { props: [prop('gone')] }),
                ],
                shopwareObject: root([node('Shopware.version')]),
            }),
            snapshot({
                components: [
                    component('sw-a', {
                        visibility: 'internal',
                        props: [prop('b', { required: true })],
                        methods: [method('m', [param('x', { type: 'number' })])],
                    }),
                    component('sw-public'),
                ],
                shopwareObject: root([node('Shopware.version')]),
            }),
        );

        expect(diff.summary.privateBySeverity).toEqual({
            breaking: 2,
            potentiallyBreaking: 1,
            compatible: 0,
            added: 0,
            newlyDeprecated: 0,
            removedDeprecated: 1,
        });
        expect(Object.values(diff.summary.privateBySeverity).reduce((sum, count) => sum + count, 0)).toBe(
            diff.summary.bySeverity.private,
        );
        expect(diff.summary.bySeverity).toMatchObject({ breaking: 1, private: 4 });
        expect(diff.summary.hasBreaking).toBe(true);
    });
});
