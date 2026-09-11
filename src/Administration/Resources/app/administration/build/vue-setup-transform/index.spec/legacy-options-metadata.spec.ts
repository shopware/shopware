/**
 * @sw-package framework
 */

import { parse } from '@babel/parser';
import { inferLegacyOptions } from '../script-analyzer/legacy-options';
import { expectVueCompilerScriptToCompile, transformOrFail } from './helpers';

function infer(source: string, publicNames: string[]) {
    return inferLegacyOptions(parse(source, { sourceType: 'module', plugins: ['typescript'] }), publicNames);
}

describe('generated legacy Options metadata', () => {
    it('classifies public declarations without exposing private bindings', () => {
        expect(
            infer(
                `
            import { ref, reactive, computed } from 'vue';
            const count = ref(0);
            const callback = ref(() => 'data');
            const state = reactive({});
            let label = 'initial';
            const doubled = computed(() => count.value * 2);
            const writable = computed({ get: () => count.value, set: value => count.value = value });
            const read = () => count.value;
            function save() {}
            const privateState = ref(0);
        `,
                [
                    'count',
                    'callback',
                    'state',
                    'label',
                    'doubled',
                    'writable',
                    'read',
                    'save',
                ],
            ),
        ).toEqual({
            members: {
                count: 'data',
                callback: 'data',
                state: 'data',
                label: 'data',
                doubled: 'computed',
                writable: 'writable-computed',
                read: 'method',
                save: 'method',
            },
            bindings: {},
        });
    });

    it('resolves Vue import aliases, namespace calls, TypeScript wrappers and identifier aliases', () => {
        expect(
            infer(
                `
            import { ref as reference, computed as derived } from 'vue';
            import * as Vue from 'vue';
            const count = reference(0) as unknown;
            const alias = count!;
            const oldCount = (alias satisfies unknown);
            const getter = () => 1;
            const doubled = derived(getter);
            const state = Vue.shallowReactive({});
        `,
                [
                    'oldCount',
                    'doubled',
                    'state',
                ],
            ),
        ).toEqual({
            members: { oldCount: 'data', doubled: 'computed', state: 'data' },
            bindings: { oldCount: 'count' },
        });
    });

    it('uses audited composable mappings for destructuring and property access', () => {
        expect(
            infer(
                `
            import useText from 'src/app/composables/use-placeholder';
            const { placeholder: translated } = useText();
            const helpers = useText();
            const placeholder = helpers.placeholder;
        `,
                [
                    'translated',
                    'placeholder',
                ],
            ),
        ).toEqual({
            members: { translated: 'method', placeholder: 'method' },
            bindings: {},
        });
    });

    it('matches actual default exports instead of assuming descriptor names are named exports', () => {
        expect(
            infer(
                `
            import { usePlaceholder } from 'src/app/composables/use-placeholder';
            import { default as defaultPlaceholder } from 'src/app/composables/use-placeholder';
            const factory = defaultPlaceholder;
            const { placeholder: unsupported } = usePlaceholder();
            const { placeholder: supported } = factory();
        `,
                [
                    'unsupported',
                    'supported',
                ],
            ),
        ).toEqual({ members: { supported: 'method' }, bindings: {} });
    });

    it('does not guess the original category of unknown factories or unaudited composables', () => {
        expect(
            infer(
                `
            import { computed } from 'some-library';
            import useNotification from 'src/app/composables/use-notification';
            const result = computed(() => 1);
            const arbitrary = customFactory();
            const { createNotificationSuccess } = useNotification();
            const { item } = arbitrary;
        `,
                [
                    'result',
                    'arbitrary',
                    'createNotificationSuccess',
                    'item',
                ],
            ),
        ).toEqual({ members: {}, bindings: {} });
    });

    it('does not link primitive copies, mutable aliases or circular declarations', () => {
        expect(
            infer('let count = 1; let alias = count; const initial = 1; const copy = initial; const a = b; const b = a;', [
                'alias',
                'copy',
                'a',
            ]),
        ).toEqual({
            members: { alias: 'data', copy: 'data' },
            bindings: {},
        });
    });

    it('accepts declared computed getters and rejects mutable factory or method classification', () => {
        expect(
            infer(
                `
            import { ref, computed } from 'vue';
            import usePlaceholder from 'src/app/composables/use-placeholder';
            let { placeholder } = usePlaceholder();
            placeholder = arbitrary;
            function getter() { return 1; }
            const derived = computed(getter);
            let factory = ref;
            factory = customFactory;
            const unknown = factory(1);
            let mutable = () => 1;
            mutable = arbitrary;
        `,
                [
                    'derived',
                    'unknown',
                    'mutable',
                    'placeholder',
                ],
            ),
        ).toEqual({ members: { derived: 'computed' }, bindings: {} });
    });

    it('does not advertise a computed setter when the setter is unknown or undefined', () => {
        expect(
            infer(
                `
            import { computed } from 'vue';
            const emptySetter = computed({ get: () => 1, set: undefined });
            const unknownSetter = computed({ get: () => 1, set: externalSetter });
            function setValue(value) {}
            const writable = computed({ get: () => 1, set: setValue });
        `,
                [
                    'emptySetter',
                    'unknownSetter',
                    'writable',
                ],
            ),
        ).toEqual({
            members: { writable: 'writable-computed' },
            bindings: {},
        });
    });

    it('preserves an own __proto__ metadata key', () => {
        const metadata = infer('const __proto__ = 1;', ['__proto__']);
        expect(Object.hasOwn(metadata.members, '__proto__')).toBe(true);
        expect(metadata.members.__proto__).toBe('data');
    });

    it('compiles generated metadata together with authored Options', () => {
        const result = transformOrFail(
            `
            <script setup lang="ts" component="sw-metadata">
            import { ref, computed } from 'vue';
            defineOptions({ inheritAttrs: false });
            const count = ref(1);
            const doubled = computed(() => count.value * 2);
            function read() { return doubled.value; }
            swDefinePublic({ count, doubled, read });
            </script>
            <template><div>{{ doubled }}</div></template>
        `,
            'sw-metadata.vue',
        );
        expect(result.code).toContain('legacyOptionsMembers: {"count":"data","doubled":"computed","read":"method"}');
        expect(result.code).toContain('...({ inheritAttrs: false })');
        expectVueCompilerScriptToCompile(result.code, 'sw-metadata.vue');
    });
});
