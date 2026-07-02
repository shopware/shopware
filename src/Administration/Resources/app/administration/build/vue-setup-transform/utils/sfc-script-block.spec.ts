/**
 * @sw-package framework
 */

import { parse } from '@vue/compiler-sfc';
import { removeSetupAttributeFromScriptBlock, toScriptBlock } from './sfc-script-block';

describe('build/vue-setup-transform/utils/sfc-script-block', () => {
    it('finds the real script tag when an earlier attribute contains a script-like string', () => {
        const source = [
            '<template>',
            '    <div data-example="<script setup>"></div>',
            '</template>',
            '<script setup lang="ts" generic="T">',
            'const count = 1;',
            '</script>',
        ].join('\n');
        const { descriptor } = parse(source);

        expect(descriptor.scriptSetup).not.toBeNull();

        if (!descriptor.scriptSetup) {
            throw new Error('Expected script setup block.');
        }

        const block = toScriptBlock(source, descriptor.scriptSetup, 'scriptSetup');
        const expectedScriptStart = source.lastIndexOf('<script setup');

        expect(block.start).toBe(expectedScriptStart);
        expect(block.content).toBe('\nconst count = 1;\n');
        expect(block.openingTagSource).toBe('<script setup lang="ts" generic="T">');
    });

    it.each([
        [
            '<script setup>',
            '<script>',
        ],
        [
            '<script lang="ts" setup>',
            '<script lang="ts">',
        ],
        [
            '<script setup="" lang="ts">',
            '<script lang="ts">',
        ],
        [
            '<script setup="true" src="./setup-helper.ts" data-name="setup">',
            '<script src="./setup-helper.ts" data-name="setup">',
        ],
    ])('removes only the setup attribute from %s', (source, expected) => {
        expect(removeSetupAttributeFromScriptBlock(source)).toBe(expected);
    });
});
