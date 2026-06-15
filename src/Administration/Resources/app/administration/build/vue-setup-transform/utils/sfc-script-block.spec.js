/**
 * @sw-package framework
 */

import { parse } from '@vue/compiler-sfc';
import { toScriptBlock } from './sfc-script-block';

describe('build/vue-setup-transform/utils/sfc-script-block', () => {
    it('finds the real script tag when an earlier attribute contains a script-like string', () => {
        const source = [
            '<template>',
            '    <div data-example="<script setup>"></div>',
            '</template>',
            '<script setup lang="ts" sw-component="sw-example" generic="T">',
            'const count = 1;',
            '</script>',
        ].join('\n');
        const { descriptor } = parse(source);

        const block = toScriptBlock(source, descriptor.scriptSetup, 'scriptSetup');
        const expectedScriptStart = source.lastIndexOf('<script setup');

        expect(block.start).toBe(expectedScriptStart);
        expect(block.content).toBe('\nconst count = 1;\n');
        expect(block.passthroughAttributesSource).toBe(' setup lang="ts" generic="T"');
    });
});
