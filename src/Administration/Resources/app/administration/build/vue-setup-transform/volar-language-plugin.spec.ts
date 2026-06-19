/**
 * @sw-package framework
 */

import shopwareSetupVolarPlugin from './volar-language-plugin';
import { parse } from '@vue/compiler-sfc';

type PluginWithOptionalParseHooks = ReturnType<typeof shopwareSetupVolarPlugin> & {
    parseSFC?: unknown,
    parseSFC2?: unknown,
};

function createScriptSetupSfc(source: string, attrs: Record<string, string> = {}) {
    const contentStart = source.indexOf('>') + 1;
    const contentEnd = source.indexOf('</script>');

    return {
        content: source,
        script: undefined,
        scriptSetup: {
            content: source.slice(contentStart, contentEnd),
            name: 'scriptSetup',
            lang: null,
            attrs,
            startTagEnd: contentStart,
            endTagStart: contentEnd,
        },
    };
}

describe('build/vue-setup-transform/volar-language-plugin', () => {
    it('does not transform SFC source so editor offsets stay stable', () => {
        const plugin = shopwareSetupVolarPlugin() as PluginWithOptionalParseHooks;

        expect(plugin.version).toBe(2.2);
        expect(plugin.name).toBe('shopware-setup');
        expect(plugin.parseSFC).toBeUndefined();
        expect(plugin.parseSFC2).toBeUndefined();
    });

    it('adds a source-mapped diagnostic embedded code for invalid setup macros', () => {
        const plugin = shopwareSetupVolarPlugin();
        const source = `<script setup sw-component="sw-my-component">
const count = 1;
swDefineOverride({ count });
</script>`;
        const sfc = createScriptSetupSfc(source, {
            'sw-component': 'sw-my-component',
        });

        const code = {
            id: 'script_ts',
            content: [],
        };

        plugin.resolveEmbeddedCode('base-override.vue', sfc, code);

        expect(code.content).toEqual([
            expect.stringContaining(
                'declare const __shopwareSetupDiagnostic_0: (value: { "Shopware setup error: swDefineOverride()',
            ),
            '__shopwareSetupDiagnostic_0(',
            [
                'swDefineOverride',
                'scriptSetup',
                source.slice(0, source.indexOf('swDefineOverride')).length - sfc.scriptSetup.startTagEnd,
                {
                    verification: true,
                },
            ],
            ');\n',
        ]);
    });

    it('does not add diagnostic embedded code for valid setup macros', () => {
        const plugin = shopwareSetupVolarPlugin();
        const source = `<script setup sw-component="sw-my-component">
const count = 1;
swDefinePublic({ count });
</script>`;
        const sfc = createScriptSetupSfc(source, {
            'sw-component': 'sw-my-component',
        });
        const code = {
            id: 'script_ts',
            content: [],
        };

        plugin.resolveEmbeddedCode('base.vue', sfc, code);

        expect(code.content).toEqual([]);
    });

    it('collects diagnostics from compiler-sfc descriptors without full SFC content', () => {
        const plugin = shopwareSetupVolarPlugin();
        const source = `<script setup sw-component="sw-my-component">
const count = 1;
swDefineOverride({ count });
</script>`;
        const sfc = parse(source, { filename: 'base-override.vue' }).descriptor;
        expect(sfc.scriptSetup).not.toBeNull();

        if (!sfc.scriptSetup) {
            throw new Error('Expected script setup block.');
        }

        const code = {
            id: 'script_ts',
            content: [],
        };

        plugin.resolveEmbeddedCode('base-override.vue', sfc, code);

        expect(code.content).toEqual([
            expect.stringContaining(
                'declare const __shopwareSetupDiagnostic_0: (value: { "Shopware setup error: swDefineOverride()',
            ),
            '__shopwareSetupDiagnostic_0(',
            [
                'swDefineOverride',
                'scriptSetup',
                source.slice(0, source.indexOf('swDefineOverride')).length - sfc.scriptSetup.loc.start.offset,
                {
                    verification: true,
                },
            ],
            ');\n',
        ]);
    });

    it('adds a diagnostic when a Shopware setup block is combined with a normal script block', () => {
        const plugin = shopwareSetupVolarPlugin();
        const source = `<script>
export default {};
</script>
<script setup sw-component="sw-my-component">
const count = 1;
swDefinePublic({ count });
</script>`;
        const sfc = parse(source, { filename: 'base-with-script.vue' }).descriptor;
        const code = {
            id: 'script_ts',
            content: [],
        };

        plugin.resolveEmbeddedCode('base-with-script.vue', sfc, code);

        expect(code.content).toEqual([
            expect.stringContaining(
                'Shopware setup error: A Shopware setup block cannot be combined with another <script> block',
            ),
            '__shopwareSetupDiagnostic_0(',
            [
                'export',
                'script',
                source.slice(0, source.indexOf('export')).length - sfc.script!.loc.start.offset,
                {
                    verification: true,
                },
            ],
            ');\n',
        ]);
    });

    it('continues collecting diagnostics after an earlier invalid macro', () => {
        const plugin = shopwareSetupVolarPlugin();
        const source = `<script setup sw-component="sw-my-component">
defineModel();
const count = 1;
swDefineOverride({ count });
</script>`;
        const sfc = parse(source, { filename: 'base-override.vue' }).descriptor;
        expect(sfc.scriptSetup).not.toBeNull();

        if (!sfc.scriptSetup) {
            throw new Error('Expected script setup block.');
        }

        const code = {
            id: 'script_ts',
            content: [],
        };

        plugin.resolveEmbeddedCode('base-override.vue', sfc, code);

        expect(code.content).toEqual([
            expect.stringContaining('Shopware setup error: Vue macro defineModel() is not supported'),
            '__shopwareSetupDiagnostic_0(',
            [
                'defineModel',
                'scriptSetup',
                source.slice(0, source.indexOf('defineModel')).length - sfc.scriptSetup.loc.start.offset,
                {
                    verification: true,
                },
            ],
            ');\n',
            expect.stringContaining('Shopware setup error: swDefineOverride() is a Shopware setup compile-time macro'),
            '__shopwareSetupDiagnostic_1(',
            [
                'swDefineOverride',
                'scriptSetup',
                source.slice(0, source.indexOf('swDefineOverride')).length - sfc.scriptSetup.loc.start.offset,
                {
                    verification: true,
                },
            ],
            ');\n',
        ]);
    });
});
