/**
 * @sw-package framework
 */

import { block, diffComponent, summarize } from './builders';

const page = block('sw_page');
const header = block('sw_page_header', { key: 'sw_page/sw_page_header', parent: 'sw_page', depth: 1 });

describe('createDiff: blocks', () => {
    it('classifies a removed block as breaking and an added block as added', () => {
        const changes = diffComponent(
            { blocks: [page, header] },
            { blocks: [page, block('sw_page_footer', { key: 'sw_page/sw_page_footer', parent: 'sw_page', depth: 1 })] },
        );

        expect(changes).toEqual([
            expect.objectContaining({
                kind: 'block',
                member: 'sw_page/sw_page_header',
                change: 'removed',
                severity: 'breaking',
                before: '<div>sw_page_header</div>',
                after: null,
            }),
            expect.objectContaining({
                kind: 'block',
                member: 'sw_page/sw_page_footer',
                change: 'added',
                severity: 'added',
                before: null,
                after: '<div>sw_page_footer</div>',
            }),
        ]);
    });

    it('classifies changed content under the same parent as compatible', () => {
        const [change] = diffComponent(
            { blocks: [page, header] },
            { blocks: [page, { ...header, content: '<h1>Title</h1>', contentHash: 'other' }] },
        );

        expect(change).toMatchObject({
            member: 'sw_page_header',
            change: 'changed',
            severity: 'compatible',
            before: '<div>sw_page_header</div>',
            after: '<h1>Title</h1>',
            reasons: ['Content changed.'],
        });
    });

    it('classifies a block moved to another parent as potentially breaking', () => {
        const toolbar = block('sw_page_toolbar', { key: 'sw_page/sw_page_toolbar', parent: 'sw_page', depth: 1 });
        const changes = diffComponent(
            { blocks: [page, toolbar, header] },
            {
                blocks: [
                    page,
                    toolbar,
                    {
                        ...header,
                        key: 'sw_page/sw_page_toolbar/sw_page_header',
                        parent: 'sw_page_toolbar',
                        depth: 2,
                    },
                ],
            },
        );

        expect(changes).toEqual([
            expect.objectContaining({
                member: 'sw_page_header',
                change: 'moved',
                severity: 'potentiallyBreaking',
                before: 'sw_page',
                after: 'sw_page/sw_page_toolbar',
                reasons: ['Moved from `sw_page` to `sw_page_toolbar`.'],
            }),
        ]);
    });

    it('adds a reason when a moved block also changed its content', () => {
        const [change] = diffComponent(
            { blocks: [page, header] },
            { blocks: [page, { ...header, key: 'sw_page_header', parent: null, depth: 0, contentHash: 'other' }] },
        );

        expect(change).toMatchObject({
            change: 'moved',
            severity: 'potentiallyBreaking',
            before: 'sw_page',
            after: '(top level)',
            reasons: [
                'Moved from `sw_page` to `(top level)`.',
                'Content changed.',
            ],
        });
    });

    it('matches blocks by key when the name is not unique', () => {
        const first = block('sw_field', { key: 'sw_page/sw_field', parent: 'sw_page', depth: 1 });
        const second = block('sw_field', { key: 'sw_page/sw_field~2', parent: 'sw_page', depth: 1 });

        const changes = diffComponent(
            { blocks: [page, first, second] },
            { blocks: [page, first, { ...second, contentHash: 'other' }] },
        );

        expect(summarize(changes)).toEqual([
            { kind: 'block', member: 'sw_page/sw_field~2', change: 'changed', severity: 'compatible' },
        ]);
    });

    it('matches by key when a unique name becomes duplicated', () => {
        const field = block('sw_field', { key: 'sw_page/sw_field', parent: 'sw_page', depth: 1 });
        const changes = diffComponent(
            { blocks: [page, field] },
            { blocks: [page, field, block('sw_field', { key: 'sw_field', parent: null, depth: 0 })] },
        );

        expect(summarize(changes)).toEqual([{ kind: 'block', member: 'sw_field', change: 'added', severity: 'added' }]);
    });

    it('applies visibility and deprecation to blocks', () => {
        const changes = diffComponent(
            {
                blocks: [
                    page,
                    { ...header, deprecation: { version: 'v6.8.0', message: '' } },
                    block('sw_internal', { visibility: 'internal' }),
                ],
            },
            { blocks: [page] },
        );

        expect(summarize(changes)).toEqual([
            { kind: 'block', member: 'sw_page/sw_page_header', change: 'removed', severity: 'removedDeprecated' },
            { kind: 'block', member: 'sw_internal', change: 'removed', severity: 'private' },
        ]);
    });
});
