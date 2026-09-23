/**
 * @sw-package framework
 *
 * Covers late binding in base components: references that run after setup read the override-aware
 * state through `__swSetupLate`, so a base-internal call dispatches to an override like `this.x()` did
 * in the Options API.
 */

import { parse } from '@vue/compiler-sfc';
import { ref } from 'vue';
import { expectVueCompilerScriptToCompile, stripIndent, transformOrFail, transformShopwareSetupSfc } from './helpers';

type State = Record<string, unknown>;

/**
 * Runs a transformed plain-JS base body against a minimal runtime that applies `overrides` to the
 * public state, and returns the destructured setup bindings.
 */
function runBaseSetup(code: string, overrides: State): State {
    const content = (parse(code).descriptor.scriptSetup?.content ?? '').replace(/^import .*$/gm, '');
    const names = /const \{\n([\s\S]*?)\n\} = __swSetupRuntime\.attach/.exec(content)?.[1].match(/\w+/g) ?? [];
    const runtime = {
        late(fallbacks: Record<string, () => unknown>) {
            let bound: State | null = null;
            const late: State = {};

            Object.keys(fallbacks).forEach((key) => {
                Object.defineProperty(late, key, { get: () => (bound ? bound[key] : fallbacks[key]()) });
            });
            Object.defineProperty(late, 'bind', { value: (state: State) => (bound = state) });

            return late;
        },
        attach(options: { public: State; private: State; late?: { bind: (state: State) => void } }) {
            const state = { ...options.private, ...options.public, ...overrides };
            options.late?.bind(state);

            return state;
        },
        expose: () => ({}),
    };
    const fakeGlobal = { Shopware: { Component: { __setupRuntime: { v1: runtime } } } };
    // eslint-disable-next-line @typescript-eslint/no-implied-eval
    const setup = new Function('globalThis', 'ref', 'defineExpose', `${content}\nreturn { ${names.join(', ')} };`) as (
        ...args: unknown[]
    ) => State;

    return setup(fakeGlobal, ref, () => undefined);
}

describe('build/vue-setup-transform base late binding', () => {
    it('dispatches a base-internal call to the override of a public function', () => {
        const source = stripIndent`
            <script setup>
            import { ref } from 'vue';

            const count = ref(1);
            function persist(value) { return 'base:' + value; }
            function save() { return persist(count.value); }

            swDefinePublic({ count, persist });
            </script>
        `;

        const result = transformOrFail(source, 'sw-late.vue');
        const state = runBaseSetup(result.code, {
            persist: (value: number) => `override:${value}`,
            count: ref(5),
        });

        expect((state.save as () => string)()).toBe('override:5');
    });

    it('rewrites references inside functions and keeps setup-time references on the alias', () => {
        const source = stripIndent`
            <script setup>
            import { computed, ref, watch } from 'vue';

            const count = ref(0);
            const label = computed(() => count.value + 1);
            const handlers = { label, onClick: () => ({ count }) };
            watch(count, () => label.value);

            swDefinePublic({ count, label });
            </script>
        `;

        const result = transformOrFail(source, 'sw-late-references.vue').code;

        expect(result).toContain('const __swSetupAuthor_label = computed(() => __swSetupLate.count.value + 1);');
        expect(result).toContain(
            'const __swSetupAuthor_handlers = { label: __swSetupAuthor_label, onClick: () => ({ count: __swSetupLate.count }) };',
        );
        expect(result).toContain('watch(__swSetupAuthor_count, () => __swSetupLate.label.value);');
        expect(result).toContain(
            'const __swSetupLate = __swSetupRuntime.late({\n    count: () => __swSetupAuthor_count,\n    label: () => __swSetupAuthor_label,\n});',
        );
        expect(result).toContain('    late: __swSetupLate,\n});');
        expectVueCompilerScriptToCompile(result, 'sw-late-references.vue');
    });

    it('omits the late object when nothing runs after setup', () => {
        const source = stripIndent`
            <script setup>
            import { ref } from 'vue';

            const count = ref(0);
            const doubled = count.value * 2;

            swDefinePublic({ count, doubled });
            </script>
        `;

        const result = transformOrFail(source, 'sw-no-late.vue').code;

        expect(result).not.toContain('__swSetupLate');
        expect(result).toContain('const __swSetupRuntime = globalThis.Shopware.Component.__setupRuntime.v1;');
    });

    it('never late-binds Vue macro bindings or references in macro arguments', () => {
        const source = stripIndent`
            <script setup lang="ts">
            const fallback = 'x';
            const props = withDefaults(defineProps<{ label?: string }>(), { label: () => fallback });
            const emit = defineEmits<{ save: [value: string] }>();
            function save(): void {
                emit('save', props.label);
            }

            swDefinePublic({ save });
            </script>
        `;

        const result = transformOrFail(source, 'sw-late-macros.vue').code;

        expect(result).toContain('{ label: () => __swSetupAuthor_fallback }');
        expect(result).toContain("__swSetupAuthor_emit('save', __swSetupAuthor_props.label);");
        expect(result).not.toContain('__swSetupLate');
        expectVueCompilerScriptToCompile(result, 'sw-late-macros.vue');
    });

    it('keeps type positions inside functions on the alias', () => {
        const source = stripIndent`
            <script setup lang="ts">
            import { ref } from 'vue';

            enum Kind { A, B }
            const count = ref(0);
            function read(kind: Kind, value: typeof count): Kind {
                return value.value > 0 ? kind : Kind.A;
            }

            swDefinePublic({ read });
            </script>
        `;

        const result = transformOrFail(source, 'sw-late-types.vue').code;

        expect(result).toContain(
            'function __swSetupAuthor_read(kind: __swSetupAuthor_Kind, value: typeof __swSetupAuthor_count): __swSetupAuthor_Kind {',
        );
        expect(result).toContain('return value.value > 0 ? kind : __swSetupLate.Kind.A;');
        expectVueCompilerScriptToCompile(result, 'sw-late-types.vue');
    });

    it.each(['let', 'var'])('rejects a top-level %s binding, which would desync from the template', (kind) => {
        const source = stripIndent`
            <script setup>
            ${kind} count = 0;
            function increment() { count += 1; }
            swDefinePublic({ increment });
            </script>
        `;

        expect(() => transformShopwareSetupSfc(source, 'sw-late-let.vue')).toThrow(
            `Top-level "${kind} count" is not supported in a base Shopware setup component.`,
        );
    });

    it('still accepts a top-level let in an override', () => {
        const source = stripIndent`
            <script setup>
            let count = 0;
            const increment = () => { count += 1; };
            swDefineOverride({ increment });
            </script>
        `;

        expect(transformOrFail(source, 'sw-late-let.override.vue').code).toContain('let count = 0;');
    });
});
