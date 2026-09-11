/** @sw-package framework */
/* eslint-disable @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-call, @typescript-eslint/no-unsafe-return */
import { mount, type VueWrapper } from '@vue/test-utils';
import { compileScript, parse } from '@vue/compiler-sfc';
import { transpileModule, ModuleKind, ScriptTarget } from 'typescript';
import { h, nextTick } from 'vue';
import { transformOrFail } from '../../../../build/vue-setup-transform/index.spec/helpers';
import { prepareLegacyComponent } from './component-definition';
import type { ComponentConfig, IndexedAwaitedComponentConfig } from 'src/core/factory/async-component.factory';

function compile(script: string, overrides: ComponentConfig[], template = '<div>{{ count }}|{{ doubled }}</div>') {
    const source = `<script setup lang="ts">${script}</script><template>${template}</template>`;
    const { code } = transformOrFail(source, 'sw-initialization-probe.vue');
    const compiled = compileScript(parse(code).descriptor, { id: 'initialization', inlineTemplate: true });
    const javascript = transpileModule(compiled.content, {
        compilerOptions: { module: ModuleKind.CommonJS, target: ScriptTarget.ES2022 },
    }).outputText;
    const exports: { default?: ComponentConfig } = {};
    // Trusted inline compiler fixture only.
    // eslint-disable-next-line @typescript-eslint/no-implied-eval
    new Function('require', 'exports', javascript)(require, exports);
    return prepareLegacyComponent(
        'sw-initialization-probe',
        exports.default!,
        overrides.map((resolvedConfig) => ({ resolvedConfig }) as IndexedAwaitedComponentConfig),
    );
}

const script = `
    import { ref, computed } from 'vue';
    defineOptions({ legacyOptionsMembers: { count: 'data', doubled: 'computed', read: 'method' } });
    const count = ref(1);
    const doubled = computed(() => count.value * 2);
    function read() { return count.value; }
    swDefinePublic({ count, doubled, read });
`;
const base: ComponentConfig = {
    data: () => ({ count: 1 }),
    methods: {
        read() {
            return this.count;
        },
    },
    computed: {
        doubled() {
            return this.count * 2;
        },
    },
    render() {
        return h('div', `${this.count}|${this.doubled}`);
    },
};

describe('native Options initialization against a compiled SFC', () => {
    const wrappers: VueWrapper[] = [];
    function render(component: ComponentConfig, options = {}) {
        const wrapper = mount(component, options);
        wrappers.push(wrapper);
        return wrapper;
    }
    afterEach(() => {
        wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
        jest.restoreAllMocks();
    });

    it('publishes base members at the same stage in beforeCreate, data, watch and created', () => {
        const events: unknown[] = [];
        const observe = (stage: string, vm: any) =>
            events.push([
                stage,
                vm.count,
                vm.doubled,
                typeof vm.read,
            ]);
        const extension: ComponentConfig = {
            beforeCreate() {
                observe('beforeCreate', this);
            },
            data() {
                observe('data', this);
                return { count: 3 };
            },
            watch: {
                count: {
                    immediate: true,
                    handler() {
                        observe('watch', this);
                    },
                },
            },
            created() {
                observe('created', this);
            },
        };
        const options = {
            global: {
                mixins: [
                    {
                        beforeCreate() {
                            observe('global beforeCreate', this);
                        },
                    },
                ],
            },
        };
        render({ extends: base, mixins: [extension] }, options);
        const expected = events.splice(0);
        render(compile(script, [extension]), options);
        expect(events).toEqual(expected);
    });

    it('lets data factories call base methods without publishing base data or computed early', () => {
        const extension: ComponentConfig = {
            data() {
                return {
                    fromMethod: (this as unknown as { read: () => unknown }).read(),
                    fromComputed: (this as unknown as { doubled: unknown }).doubled,
                };
            },
        };
        const baseline = render({ extends: base, mixins: [extension] });
        const migrated = render(compile(script, [extension]));
        expect((migrated.vm.$.proxy as any).fromMethod).toBe((baseline.vm as any).fromMethod);
        expect((migrated.vm.$.proxy as any).fromComputed).toBe((baseline.vm as any).fromComputed);
    });

    it('preserves aliases during initialization and tracks later data replacement in SFC computed values', async () => {
        const events: unknown[] = [];
        const extension: ComponentConfig = {
            beforeCreate() {
                events.push(this.oldCount);
            },
            data() {
                events.push(this.oldCount);
                return { oldCount: 3 };
            },
            watch: {
                oldCount: {
                    immediate: true,
                    handler(value) {
                        events.push(value);
                    },
                },
            },
        };
        const migrated = render(
            compile(
                script
                    .replace("count: 'data'", "oldCount: 'data'")
                    .replace(
                        'defineOptions({ legacyOptionsMembers:',
                        "defineOptions({ legacyOptionsBindings: { oldCount: 'count' }, legacyOptionsMembers:",
                    ),
                [extension],
            ),
        );
        expect(events).toEqual([
            undefined,
            undefined,
            3,
        ]);
        expect(migrated.text()).toBe('3|6');
        (migrated.vm.$.proxy as any).$data.oldCount = 5;
        await nextTick();
        expect(migrated.text()).toBe('5|10');
        expect(events).toEqual([
            undefined,
            undefined,
            3,
            5,
        ]);
    });
});
