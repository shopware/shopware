/**
 * @sw-package framework
 */

import {
    BLOCK_INSPECTOR_STORAGE_KEY,
    getInspectedBlock,
    getInspectedBlocks,
    isBlockInspectorEnabled,
    markBlockElements,
    mergeBlockMarker,
    parseBlockMarker,
    registerInspectedBlock,
    resetBlockInspector,
    setBlockInspectorEnabled,
} from './block-inspector';

describe('core/factory/block-inspector', () => {
    beforeEach(() => {
        localStorage.removeItem(BLOCK_INSPECTOR_STORAGE_KEY);
        resetBlockInspector();
    });

    describe('enabled flag', () => {
        it('is off by default', () => {
            expect(isBlockInspectorEnabled()).toBe(false);
        });

        it('reads the stored flag once at first use', () => {
            localStorage.setItem(BLOCK_INSPECTOR_STORAGE_KEY, 'true');

            expect(isBlockInspectorEnabled()).toBe(true);

            localStorage.removeItem(BLOCK_INSPECTOR_STORAGE_KEY);

            // A template rendered with markers must not lose them mid-session.
            expect(isBlockInspectorEnabled()).toBe(true);
        });

        it('persists the flag for the next boot and applies it right away', () => {
            setBlockInspectorEnabled(true);

            expect(isBlockInspectorEnabled()).toBe(true);
            expect(localStorage.getItem(BLOCK_INSPECTOR_STORAGE_KEY)).toBe('true');

            setBlockInspectorEnabled(false);

            expect(isBlockInspectorEnabled()).toBe(false);
            expect(localStorage.getItem(BLOCK_INSPECTOR_STORAGE_KEY)).toBeNull();
        });
    });

    describe('registry', () => {
        it('keeps the first registration of a block', () => {
            registerInspectedBlock({ name: 'a', component: 'sw-first', kind: 'twig' });
            registerInspectedBlock({ name: 'a', component: 'sw-second', kind: 'native' });

            expect(getInspectedBlock('a')).toEqual({ name: 'a', component: 'sw-first', kind: 'twig' });
            expect(getInspectedBlocks().size).toBe(1);
        });

        it('returns undefined for unknown blocks', () => {
            expect(getInspectedBlock('missing')).toBeUndefined();
        });
    });

    describe('marker values', () => {
        it('parses and merges names without duplicates', () => {
            expect(parseBlockMarker(' inner  outer ')).toEqual([
                'inner',
                'outer',
            ]);
            expect(parseBlockMarker(null)).toEqual([]);
            expect(mergeBlockMarker('inner', 'outer')).toBe('inner outer');
            expect(mergeBlockMarker('inner outer', 'inner')).toBe('inner outer');
            expect(mergeBlockMarker(undefined, 'only')).toBe('only');
        });
    });

    describe('markBlockElements', () => {
        it('marks every top-level element and leaves nested ones alone', () => {
            const html = '<div class="a"><span>x</span></div> <p>y</p>';

            expect(markBlockElements(html, 'blk')).toBe(
                '<div data-sw-block="blk" class="a"><span>x</span></div> <p data-sw-block="blk">y</p>',
            );
        });

        it('extends an existing marker instead of adding a second attribute', () => {
            const html = '<div data-sw-block="inner">x</div>';

            expect(markBlockElements(html, 'outer')).toBe('<div data-sw-block="inner outer">x</div>');
        });

        it('handles void and self-closing elements as siblings', () => {
            const html = '<input v-model="a"><br/><mt-icon name="x" /><i>z</i>';

            expect(markBlockElements(html, 'blk')).toBe(
                '<input data-sw-block="blk" v-model="a"><br data-sw-block="blk"/><mt-icon data-sw-block="blk" name="x" /><i data-sw-block="blk">z</i>',
            );
        });

        it('treats a top-level template as transparent and marks its children', () => {
            const html = '<template v-if="show"><div>a</div><div>b</div></template><template #footer><b>c</b></template>';

            expect(markBlockElements(html, 'blk')).toBe(
                '<template v-if="show"><div data-sw-block="blk">a</div><div data-sw-block="blk">b</div></template><template #footer><b data-sw-block="blk">c</b></template>',
            );
        });

        it('does not mark a template nested inside an element', () => {
            const html = '<sw-card><template #title><b>t</b></template></sw-card>';

            expect(markBlockElements(html, 'blk')).toBe(
                '<sw-card data-sw-block="blk"><template #title><b>t</b></template></sw-card>',
            );
        });

        it('marks the content of a generated sw-block wrapper, not the wrapper', () => {
            const html =
                '<sw-block name="inner" :data="$dataScope" :sw-internal-legacy-shim="false"><p data-sw-block="inner">x</p></sw-block>';

            expect(markBlockElements(html, 'outer')).toBe(
                '<sw-block name="inner" :data="$dataScope" :sw-internal-legacy-shim="false"><p data-sw-block="inner outer">x</p></sw-block>',
            );
        });

        it('skips built-ins that turn attributes into something else', () => {
            const html = '<slot name="x"></slot><transition name="fade"><div>a</div></transition>';

            expect(markBlockElements(html, 'blk')).toBe(html);
        });

        it('ignores comments, interpolations and quoted angle brackets', () => {
            const html = '<!-- <div> --> {{ a < b }} <div v-if="a > b" title=\'>\'>x</div>';

            expect(markBlockElements(html, 'blk')).toBe(
                '<!-- <div> --> {{ a < b }} <div data-sw-block="blk" v-if="a > b" title=\'>\'>x</div>',
            );
        });

        it('returns text-only blocks unchanged', () => {
            expect(markBlockElements('  {{ label }} ', 'blk')).toBe('  {{ label }} ');
            expect(markBlockElements('', 'blk')).toBe('');
        });
    });
});
