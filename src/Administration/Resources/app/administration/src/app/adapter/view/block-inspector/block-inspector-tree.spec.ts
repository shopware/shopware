/**
 * @sw-package framework
 */

import {
    blockNameFromNodeId,
    blockNodeId,
    buildBlockTree,
    pickedNodeId,
    TAG_EXTENDED,
    TAG_NATIVE,
    TAG_PICKED,
    TAG_TWIG,
    type TreeBlock,
} from './block-inspector-tree';

const blocks: TreeBlock[] = [
    { name: 'sw_product_detail_base', component: 'sw-product-detail-base', kind: 'twig', extended: false },
    { name: 'sw_product_detail_base_price_form', component: 'sw-product-detail-base', kind: 'twig', extended: true },
    { name: 'converted-block', component: 'sw-converted', kind: 'native', extended: false },
];

describe('adapter/view/block-inspector/block-inspector-tree', () => {
    describe('node ids', () => {
        it('carry the generation and resolve back to the block name', () => {
            expect(blockNodeId('a', 3)).toBe('3|block:a');
            expect(pickedNodeId('a', 3)).toBe('3|picked:a');
            expect(blockNameFromNodeId('3|block:a')).toBe('a');
            expect(blockNameFromNodeId('3|picked:a')).toBe('a');
        });

        it('resolve ids without a generation and reject other nodes', () => {
            expect(blockNameFromNodeId('block:a')).toBe('a');
            expect(blockNameFromNodeId('3|component:sw-x')).toBeNull();
            expect(blockNameFromNodeId('disabled')).toBeNull();
        });
    });

    describe('buildBlockTree', () => {
        it('groups blocks under their component with kind and extension tags', () => {
            expect(buildBlockTree(blocks, { generation: 1 })).toEqual([
                {
                    id: '1|component:sw-product-detail-base',
                    label: 'sw-product-detail-base',
                    children: [
                        { id: '1|block:sw_product_detail_base', label: 'sw_product_detail_base', tags: [TAG_TWIG] },
                        {
                            id: '1|block:sw_product_detail_base_price_form',
                            label: 'sw_product_detail_base_price_form',
                            tags: [
                                TAG_TWIG,
                                TAG_EXTENDED,
                            ],
                        },
                    ],
                },
                {
                    id: '1|component:sw-converted',
                    label: 'sw-converted',
                    children: [{ id: '1|block:converted-block', label: 'converted-block', tags: [TAG_NATIVE] }],
                },
            ]);
        });

        it('filters by block or component name, case-insensitively', () => {
            expect(buildBlockTree(blocks, { generation: 1, filter: 'PRICE' }).map((node) => node.label)).toEqual([
                'sw-product-detail-base',
            ]);
            expect(buildBlockTree(blocks, { generation: 1, filter: 'converted' })[0]?.children?.map((n) => n.label)).toEqual(
                [
                    'converted-block',
                ],
            );
            expect(buildBlockTree(blocks, { generation: 1, filter: 'nothing' })).toEqual([]);
        });

        it('puts the picked block first with its enclosing blocks as children, ignoring the filter', () => {
            const tree = buildBlockTree(blocks, {
                generation: 2,
                filter: 'converted',
                pick: {
                    blockName: 'sw_product_detail_base_price_form',
                    enclosingBlockNames: [
                        'sw_product_detail_base_price_form',
                        'sw_product_detail_base',
                    ],
                },
            });

            expect(tree[0]).toEqual({
                id: '2|picked:sw_product_detail_base_price_form',
                label: 'sw_product_detail_base_price_form',
                tags: [
                    TAG_PICKED,
                    TAG_TWIG,
                    TAG_EXTENDED,
                ],
                children: [{ id: '2|picked:sw_product_detail_base', label: 'sw_product_detail_base', tags: [TAG_TWIG] }],
            });
            expect(tree.map((node) => node.label)).toEqual([
                'sw_product_detail_base_price_form',
                'sw-converted',
            ]);
        });

        it('changes every node id with the generation so a stale selection cannot survive a pick', () => {
            const before = buildBlockTree(blocks, { generation: 1 }).flatMap((node) => [
                node.id,
                ...(node.children ?? []).map((child) => child.id),
            ]);
            const after = buildBlockTree(blocks, { generation: 2 }).flatMap((node) => [
                node.id,
                ...(node.children ?? []).map((child) => child.id),
            ]);

            expect(before.some((id) => after.includes(id))).toBe(false);
        });

        it('still lists a picked block that has left the DOM, without kind tags', () => {
            const tree = buildBlockTree([], { generation: 1, pick: { blockName: 'gone', enclosingBlockNames: ['gone'] } });

            expect(tree).toEqual([{ id: '1|picked:gone', label: 'gone', tags: [TAG_PICKED], children: [] }]);
        });
    });
});
