/**
 * @sw-package framework
 */

import { MULTI_ROOT, assertSingleRoot } from './assert-single-root';

/** Both inputs are the same markup unless a test needs the guard insertion to differ. */
function inspect(converted: string, normalized: string = converted): string[] {
    return assertSingleRoot(converted, normalized);
}

describe('assert-single-root', () => {
    it('accepts a single block around a single element', () => {
        expect(inspect('<sw-block name="a"><div>content</div></sw-block>')).toEqual([]);
    });

    it('accepts nested blocks around a single element', () => {
        expect(inspect('<sw-block name="a"><sw-block name="b"><div>content</div></sw-block></sw-block>')).toEqual([]);
    });

    it('ignores whitespace and comments when counting roots', () => {
        expect(inspect('\n<!-- note -->\n<sw-block name="a">\n<div>content</div>\n</sw-block>\n')).toEqual([]);
    });

    it('reports a second top-level block that used to render nothing', () => {
        expect(inspect('<sw-block name="a"><div>a</div></sw-block><sw-block name="b"></sw-block>')).toEqual([MULTI_ROOT]);
    });

    it('accepts two top-level blocks that each rendered a root of their own', () => {
        expect(inspect('<sw-block name="a"><div>a</div></sw-block><sw-block name="b"><div>b</div></sw-block>')).toEqual([]);
    });

    it('reports a v-if chain the conversion split across two blocks', () => {
        const converted =
            '<sw-block name="a"><div v-if="on">a</div></sw-block><sw-block name="b"><div v-else>b</div></sw-block>';
        const normalized =
            '<sw-block name="a"><div v-if="on">a</div></sw-block><sw-block name="b"><template v-if="(on)"><!-- guard --></template><div v-else>b</div></sw-block>';

        expect(inspect(converted, normalized)).toEqual([MULTI_ROOT]);
    });

    it('accepts a block that already held several elements', () => {
        expect(inspect('<sw-block name="a"><div>a</div><div>b</div></sw-block>')).toEqual([]);
    });

    it('accepts a top-level v-if chain that a single block keeps together', () => {
        expect(inspect('<sw-block name="a"><div v-if="on">a</div><div v-else>b</div></sw-block>')).toEqual([]);
    });

    it('accepts markup without any block', () => {
        expect(inspect('<div>content</div>')).toEqual([]);
    });
});
