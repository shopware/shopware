/**
 * @sw-package framework
 */

import { moveRootCommentsIntoBlock } from './move-root-comments';

describe('move-root-comments', () => {
    it('moves a leading comment into the only block', () => {
        expect(
            moveRootCommentsIntoBlock('<!-- @deprecated -->\n<sw-block name="a">\n    <div>content</div>\n</sw-block>'),
        ).toBe('<sw-block name="a">\n    <!-- @deprecated -->\n<div>content</div>\n</sw-block>');
    });

    it('moves a trailing comment into the only block', () => {
        expect(moveRootCommentsIntoBlock('<sw-block name="a">\n    <div>c</div>\n</sw-block>\n<!-- note -->')).toBe(
            '<sw-block name="a">\n    <!-- note -->\n<div>c</div>\n</sw-block>',
        );
    });

    it('keeps several comments in the order they were written', () => {
        expect(moveRootCommentsIntoBlock('<!-- one -->\n<!-- two -->\n<sw-block name="a"><div>c</div></sw-block>')).toBe(
            '<sw-block name="a"><!-- one -->\n<!-- two -->\n<div>c</div></sw-block>',
        );
    });

    it('leaves a template whose comments are already inside the block alone', () => {
        const template = '<sw-block name="a"><!-- note --><div>c</div></sw-block>';

        expect(moveRootCommentsIntoBlock(template)).toBe(template);
    });

    it('leaves a template that renders several roots alone, because it has no root to restore', () => {
        const template =
            '<!-- note -->\n<sw-block name="a"><div>a</div></sw-block>\n<sw-block name="b"><div>b</div></sw-block>';

        expect(moveRootCommentsIntoBlock(template)).toBe(template);
    });

    it('leaves a root that is not a converted block alone', () => {
        const template = '<!-- note -->\n<div>content</div>';

        expect(moveRootCommentsIntoBlock(template)).toBe(template);
    });

    it('leaves an empty block alone, because it renders a fragment either way', () => {
        const template = '<!-- note -->\n<sw-block name="a"></sw-block>';

        expect(moveRootCommentsIntoBlock(template)).toBe(template);
    });

    // Moving one would silence a line it was never written for.
    it('leaves an eslint directive where the author put it', () => {
        const template = '<!-- eslint-disable-next-line vue/no-v-html -->\n<sw-block name="a"><div>c</div></sw-block>';

        expect(moveRootCommentsIntoBlock(template)).toBe(template);
    });

    it('leaves markup Vue cannot parse alone', () => {
        const template = '<!-- note --><sw-block name="a"><div></sw-block>';

        expect(moveRootCommentsIntoBlock(template)).toBe(template);
    });
});
