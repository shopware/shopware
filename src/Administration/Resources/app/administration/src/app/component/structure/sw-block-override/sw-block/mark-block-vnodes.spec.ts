/**
 * @sw-package framework
 */

import { createCommentVNode, createTextVNode, Fragment, h, type VNode } from 'vue';
import markBlockVNodes from './mark-block-vnodes';

const marker = (node: VNode): unknown => node.props?.['data-sw-block'];

describe('sw-block/mark-block-vnodes', () => {
    it('marks a single element root', () => {
        const marked = markBlockVNodes(h('div', { class: 'a' }), 'blk');

        expect(marker(marked)).toBe('blk');
        expect(marked.props?.class).toBe('a');
    });

    it('marks every element and component in an array of roots and keeps text and comments', () => {
        const component = { name: 'child', render: () => h('span') };
        const nodes = [
            h('p'),
            createTextVNode('text'),
            createCommentVNode('note'),
            h(component),
        ];

        const marked = markBlockVNodes(nodes, 'blk') as VNode[];

        expect(marker(marked[0])).toBe('blk');
        expect(marked[1]).toBe(nodes[1]);
        expect(marked[2]).toBe(nodes[2]);
        expect(marker(marked[3])).toBe('blk');
    });

    it('walks into fragments', () => {
        const fragment = h(Fragment, [
            h('li'),
            h('li'),
        ]);

        const marked = markBlockVNodes(fragment, 'blk');
        const children = marked.children as VNode[];

        expect(marked.type).toBe(Fragment);
        expect(children.map(marker)).toEqual([
            'blk',
            'blk',
        ]);
    });

    it('extends a marker the Twig marker already rendered', () => {
        const marked = markBlockVNodes(h('div', { 'data-sw-block': 'inner' }), 'outer');

        expect(marker(marked)).toBe('inner outer');
    });

    it('passes null through', () => {
        expect(markBlockVNodes(null, 'blk')).toBeNull();
    });
});
