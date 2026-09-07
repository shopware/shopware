/**
 * @sw-package framework
 */
import { createCommentVNode, h } from 'vue';
import reduceToSingleRoot from './reduce-to-single-root';

describe('reduce-to-single-root', () => {
    it('returns a lone node instead of the array around it', () => {
        const node = h('div');

        expect(reduceToSingleRoot([node])).toBe(node);
    });

    it('leaves several nodes as they are', () => {
        const nodes = [
            h('div'),
            h('span'),
        ];

        expect(reduceToSingleRoot(nodes)).toBe(nodes);
    });

    it('leaves an empty list as it is', () => {
        const nodes: never[] = [];

        expect(reduceToSingleRoot(nodes)).toBe(nodes);
    });

    it('passes anything that is not an array straight through', () => {
        expect(reduceToSingleRoot(null)).toBeNull();
        expect(reduceToSingleRoot(undefined)).toBeUndefined();
    });

    it('does not count an author comment as a root', () => {
        const node = h('div');

        expect(
            reduceToSingleRoot([
                createCommentVNode('a note'),
                node,
            ]),
        ).toBe(node);
    });

    // The root shape has to survive the condition flipping: a placeholder that stops counting would
    // make the same markup single-rooted while falsy and multi-rooted while truthy, and Vue answers
    // a changed root type with an unmount plus remount.
    it.each([
        [
            'development',
            'v-if',
        ],
        [
            'production',
            '',
        ],
    ])('counts the %s `v-if` placeholder as a root', (_build, content) => {
        const nodes = [
            createCommentVNode(content),
            h('div'),
        ];

        expect(reduceToSingleRoot(nodes)).toBe(nodes);
    });

    it('returns a lone `v-if` placeholder, so a falsy branch still has one root', () => {
        const placeholder = createCommentVNode('v-if');

        expect(reduceToSingleRoot([placeholder])).toBe(placeholder);
    });

    it('leaves a list holding anything that is not a vnode alone', () => {
        const nodes = ['text'] as never;

        expect(reduceToSingleRoot(nodes)).toBe(nodes);
    });
});
