/**
 * @sw-package framework
 *
 * Covers the override's module-scope registration: the generated plain `<script>` registers the
 * callback when the module is evaluated, and the template still resolves the module-scope bindings
 * through the generated `<script setup>`.
 */

import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc';
import { stripIndent, transformOrFail } from './helpers';

const source = stripIndent`
    <template>
    <sw-block extends="sw_example_component_body">
        <p>{{ info }} {{ format(info) }}</p>
    </sw-block>
    </template>
    <script setup lang="ts">
    import { format } from './format';

    const info = 'local';

    swDefineOverride({});
    </script>
`;

function compile(code: string, inlineTemplate: boolean): string {
    const { descriptor } = parse(code, { filename: 'sw-thing.override.vue' });
    const script = compileScript(descriptor, { id: 'sw-thing', inlineTemplate });

    if (inlineTemplate) {
        return script.content;
    }

    return compileTemplate({
        source: descriptor.template?.content ?? '',
        filename: 'sw-thing.override.vue',
        id: 'sw-thing',
        compilerOptions: { bindingMetadata: script.bindings },
    }).code;
}

describe('build/vue-setup-transform override module-scope registration', () => {
    it('registers the override from a plain script and keeps a script setup for the template', () => {
        const result = transformOrFail(source, 'sw-thing.override.vue').code;

        expect(result).toContain('<script lang="ts">\nimport { format } from \'./format\';');
        expect(result).toMatch(/<\/script>\n<script setup lang="ts">\/\*[^<]*\*\/<\/script>$/);

        const { descriptor } = parse(result, { filename: 'sw-thing.override.vue' });
        const script = compileScript(descriptor, { id: 'sw-thing' }).content;

        expect(script.indexOf('.override(')).toBeGreaterThan(-1);
        expect(script.indexOf('.override(')).toBeLessThan(script.indexOf('setup('));
    });

    it.each([false, true])('resolves module-scope bindings from the template (inline template: %s)', (inline) => {
        const render = compile(transformOrFail(source, 'sw-thing.override.vue').code, inline);

        expect(render).not.toContain('_ctx.__swSetupNamespace');
        expect(render).not.toContain('_ctx.format');
        expect(render).toMatch(/\[(\$setup\.|_unref\()__swSetupNamespace\)?\]: \{ info \} = \{\}/);
    });

    it('declares the namespace symbol at module scope, before the registered callback', () => {
        const result = transformOrFail(source, 'sw-thing.override.vue').code;

        // Created once per module: a symbol created in the callback would differ per base instance.
        expect(result).toContain("const __swSetupNamespace = Symbol('sw-thing.override');");
        expect(result.indexOf('const __swSetupNamespace')).toBeLessThan(result.indexOf('.override('));
        expect(result).toContain('[__swSetupNamespace]: {');
    });

    it('derives a short file key from the filename without leaking the path', () => {
        const key = (filename: string) =>
            /\.override\('sw-thing', '([^']+)'/.exec(transformOrFail(source, filename).code)?.[1];
        const absolute = `${process.cwd()}/src/deep/path/sw-thing.override.vue`;

        expect(key(absolute)).toMatch(/^[0-9a-f]{8}$/);
        expect(key(absolute)).toBe(key('src/deep/path/sw-thing.override.vue'));
        expect(key(absolute)).not.toBe(key('src/other/sw-thing.override.vue'));
        expect(transformOrFail(source, absolute).code).not.toContain('deep/path');
    });

    it('keeps a comment-only template for an override without one', () => {
        const result = transformOrFail(
            stripIndent`
                <script setup>
                swDefineOverride({});
                </script>
            `,
            'sw-thing.override.vue',
        ).code;

        // The registration component is still mounted in the hidden container.
        expect(result.startsWith('<template><!-- Shopware override registration component --></template>\n')).toBe(true);
    });
});
