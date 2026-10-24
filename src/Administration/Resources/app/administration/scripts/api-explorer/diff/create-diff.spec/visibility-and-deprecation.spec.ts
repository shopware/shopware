/**
 * @sw-package framework
 */

import { block, component, diffComponent, diffComponents, method, named, param, prop } from './builders';

const deprecated = { version: 'v6.8.0', message: 'Use sw-bar instead.' };

describe('createDiff: visibility', () => {
    it.each([
        'private',
        'internal',
        'experimental',
    ] as const)('classifies removing a member of an @%s component as private', (visibility) => {
        const [change] = diffComponent({ visibility, props: [prop('value')] }, { visibility });

        expect(change).toMatchObject({
            kind: 'prop',
            change: 'removed',
            severity: 'private',
            reasons: [
                `Removed; it was @${visibility}.`,
                'Removed without prior deprecation.',
            ],
        });
    });

    it('classifies removing an @internal member of a public component as private', () => {
        const [change] = diffComponent({ methods: [method('load', [], { visibility: 'internal' })] }, {});

        expect(change).toMatchObject({
            change: 'removed',
            severity: 'private',
            reasons: ['Removed; it was @internal.', 'Removed without prior deprecation.'],
        });
    });

    it('classifies removing and adding non-public components as private', () => {
        const changes = diffComponents(
            [component('sw-old', { visibility: 'private' })],
            [component('sw-new', { visibility: 'experimental' })],
        );

        expect(changes).toEqual([
            expect.objectContaining({
                member: 'sw-new',
                change: 'added',
                severity: 'private',
                reasons: ['Added as @experimental.'],
            }),
            expect.objectContaining({
                member: 'sw-old',
                change: 'removed',
                severity: 'private',
                reasons: ['Removed; it was @private.', 'Removed without prior deprecation.'],
            }),
        ]);
    });

    it('classifies a new required prop as breaking when its public component is made private at the same time', () => {
        const changes = diffComponent({}, { visibility: 'private', props: [prop('p', { required: true })] });

        expect(changes).toEqual([
            expect.objectContaining({
                kind: 'prop',
                change: 'added',
                severity: 'breaking',
                reasons: ['New required prop.'],
            }),
            expect.objectContaining({
                kind: 'component',
                severity: 'potentiallyBreaking',
                reasons: ['Now marked @private.'],
            }),
        ]);
    });

    it('classifies a new required prop as compatible when its private component is made public', () => {
        const changes = diffComponent({ visibility: 'private' }, { props: [prop('p', { required: true })] });

        expect(changes).toEqual([
            expect.objectContaining({
                kind: 'prop',
                change: 'added',
                severity: 'compatible',
                reasons: ['Added; its owner was @private.'],
            }),
            expect.objectContaining({
                kind: 'component',
                severity: 'compatible',
                reasons: ['Now public; it was @private.'],
            }),
        ]);
    });

    it('uses the head visibility for additions', () => {
        const changes = diffComponent(
            { visibility: 'internal' },
            { visibility: 'internal', props: [prop('value', { required: true })] },
        );

        expect(changes).toEqual([
            expect.objectContaining({
                kind: 'prop',
                change: 'added',
                severity: 'private',
                reasons: ['Added as @internal.', 'New required prop.'],
            }),
        ]);
    });

    it('classifies a breaking change of a non-public member as private and names the visibility', () => {
        const [change] = diffComponent(
            { methods: [method('load', [param('id')], { visibility: 'internal' })] },
            { methods: [method('load', [], { visibility: 'internal' })] },
        );

        expect(change).toMatchObject({
            severity: 'private',
            reasons: [
                'Not public; it is @internal.',
                'Required parameter `id` removed.',
            ],
        });
    });

    it('classifies making a component private as potentially breaking without repeating it for its members', () => {
        const changes = diffComponent({ props: [prop('value')] }, { visibility: 'private', props: [prop('value')] });

        expect(changes).toEqual([
            expect.objectContaining({
                kind: 'component',
                change: 'changed',
                severity: 'potentiallyBreaking',
                reasons: ['Now marked @private.'],
            }),
        ]);
    });

    it('classifies making a member internal as potentially breaking', () => {
        const [change] = diffComponent(
            { emits: [named('change')] },
            { emits: [named('change', { visibility: 'internal' })] },
        );

        expect(change).toMatchObject({ kind: 'emit', severity: 'potentiallyBreaking', reasons: ['Now marked @internal.'] });
    });

    it('keeps a breaking severity when a member is made private and broken at the same time', () => {
        const [change] = diffComponent(
            { methods: [method('load', [param('id')])] },
            { methods: [method('load', [], { visibility: 'private' })] },
        );

        expect(change).toMatchObject({
            severity: 'breaking',
            reasons: [
                'Required parameter `id` removed.',
                'Now marked @private.',
            ],
        });
    });

    it('classifies making something public as compatible', () => {
        const changes = diffComponent(
            { visibility: 'experimental', methods: [method('load', [param('id')])] },
            { methods: [method('load', [])] },
        );

        expect(changes).toEqual([
            expect.objectContaining({
                kind: 'method',
                severity: 'compatible',
                reasons: ['Required parameter `id` removed.'],
            }),
            expect.objectContaining({
                kind: 'component',
                severity: 'compatible',
                reasons: ['Now public; it was @experimental.'],
            }),
        ]);
    });

    it('reports nothing for unchanged members of a non-public component', () => {
        expect(
            diffComponent({ visibility: 'internal', props: [prop('a')] }, { visibility: 'internal', props: [prop('a')] }),
        ).toEqual([]);
    });
});

describe('createDiff: deprecation', () => {
    it('classifies removing a deprecated component as removedDeprecated', () => {
        const [change] = diffComponents([component('sw-foo', { deprecation: deprecated })], []);

        expect(change).toMatchObject({
            kind: 'component',
            change: 'removed',
            severity: 'removedDeprecated',
            before: 'register',
            after: null,
            reasons: ['Removed; it was deprecated since v6.8.0.'],
        });
    });

    it('classifies removing a member deprecated without version as removedDeprecated', () => {
        const [change] = diffComponent({ slots: [named('footer', { deprecation: { version: null, message: '' } })] }, {});

        expect(change).toMatchObject({ severity: 'removedDeprecated', reasons: ['Removed; it was deprecated.'] });
    });

    it('classifies a newly deprecated component as newlyDeprecated', () => {
        const [change] = diffComponent({}, { deprecation: deprecated });

        expect(change).toMatchObject({
            kind: 'component',
            change: 'deprecated',
            severity: 'newlyDeprecated',
            reasons: ['Newly deprecated since v6.8.0: Use sw-bar instead.'],
        });
    });

    it('classifies a removed deprecation of a kept entry as compatible', () => {
        const [change] = diffComponent(
            { inject: [named('repositoryFactory', { deprecation: deprecated })] },
            { inject: [named('repositoryFactory')] },
        );

        expect(change).toMatchObject({
            kind: 'inject',
            change: 'changed',
            severity: 'compatible',
            reasons: ['Deprecation removed.'],
        });
    });

    it('ignores an unchanged deprecation', () => {
        expect(diffComponent({ deprecation: deprecated }, { deprecation: { ...deprecated } })).toEqual([]);
    });

    it('lets a higher severity win over a new deprecation', () => {
        const [change] = diffComponent(
            { props: [prop('value')] },
            { props: [prop('value', { required: true, deprecation: deprecated })] },
        );

        expect(change).toMatchObject({ change: 'changed', severity: 'breaking' });
    });

    it('classifies removing a member or block of a deprecated component as removedDeprecated', () => {
        const changes = diffComponent(
            { deprecation: deprecated, props: [prop('value')], blocks: [block('sw_foo')] },
            { deprecation: deprecated },
        );

        expect(changes).toEqual([
            expect.objectContaining({
                kind: 'block',
                member: 'sw_foo',
                severity: 'removedDeprecated',
                reasons: ['Removed; component `sw-foo` is deprecated since v6.8.0.'],
            }),
            expect.objectContaining({
                kind: 'prop',
                member: 'value',
                severity: 'removedDeprecated',
                reasons: ['Removed; component `sw-foo` is deprecated since v6.8.0.'],
            }),
        ]);
    });
});
