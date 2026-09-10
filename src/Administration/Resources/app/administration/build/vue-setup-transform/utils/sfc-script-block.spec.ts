/**
 * @sw-package framework
 */

import { parse } from '@vue/compiler-sfc';
import { toScriptBlock } from './sfc-script-block';

describe('build/vue-setup-transform/utils/sfc-script-block', () => {
    it('takes both content boundaries from Vue, so a script-like attribute cannot mislead it', () => {
        const source = [
            '<template>',
            '    <div data-example="<script setup>"></div>',
            '</template>',
            '<script setup lang="ts" generic="T">',
            'const count = 1;',
            '</script>',
        ].join('\n');
        const { descriptor } = parse(source);

        // Asserts the block is present AND narrows it non-null for `toScriptBlock` below. (A separate
        // `expect(...).not.toBeNull()` would make this throw unreachable, so it is the sole check.)
        if (!descriptor.scriptSetup) {
            throw new Error('Expected a <script setup> block.');
        }

        const block = toScriptBlock(descriptor.scriptSetup, 'scriptSetup');

        expect(block.content).toBe('\nconst count = 1;\n');
        expect(source.slice(block.contentStart, block.contentEnd)).toBe(block.content);
        expect(block.lang).toBe('ts');
    });
});
