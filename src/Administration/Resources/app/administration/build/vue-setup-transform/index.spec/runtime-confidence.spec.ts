/** @sw-package framework */
/* eslint-disable @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-call, @typescript-eslint/no-unsafe-return */
import { mount, type VueWrapper } from '@vue/test-utils';
import { compileScript, parse } from '@vue/compiler-sfc';
import { transpileModule, ModuleKind, ScriptTarget } from 'typescript';
import { h, nextTick } from 'vue';
import { transformOrFail } from './helpers';
import { prepareLegacyComponent } from 'src/app/adapter/options-composition-shim/component-definition';
import type { ComponentConfig, IndexedAwaitedComponentConfig } from 'src/core/factory/async-component.factory';

function compile(script: string, overrides: ComponentConfig[], template = '<div>{{ count }}</div>') {
    const source = `<script setup lang="ts">${script}</script><template>${template}</template>`;
    const { code } = transformOrFail(source, 'sw-runtime-confidence.vue');
    const compiled = compileScript(parse(code).descriptor, { id: 'confidence', inlineTemplate: true });
    const javascript = transpileModule(compiled.content, {
        compilerOptions: { module: ModuleKind.CommonJS, target: ScriptTarget.ES2022 },
    }).outputText;
    const exports: { default?: ComponentConfig } = {};
    // Trusted inline compiler fixture only.
    // eslint-disable-next-line @typescript-eslint/no-implied-eval
    new Function('require', 'exports', javascript)(require, exports);
    return prepareLegacyComponent(
        'sw-runtime-confidence',
        exports.default!,
        overrides.map((resolvedConfig) => ({ resolvedConfig }) as IndexedAwaitedComponentConfig),
    );
}

const script = `import { ref } from 'vue'; const count = ref(1); swDefinePublic({ count });`;

describe('adversarial public legacy contract comparison', () => {
    const wrappers: VueWrapper[] = [];
    function render(component: ComponentConfig) {
        const wrapper = mount(component);
        wrappers.push(wrapper);
        return wrapper;
    }
    afterEach(() => {
        wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
    });

    it('keeps plugin-added methods, data and computed visible through parent template refs', () => {
        const extension: ComponentConfig = {
            data: () => ({ extensionValue: 7 }),
            methods: {
                extensionRead() {
                    return this.extensionValue;
                },
            },
            computed: {
                extensionDouble() {
                    return this.extensionValue * 2;
                },
            },
        };
        const baseline = render({
            data: () => ({ count: 1 }),
            mixins: [extension],
            render() {
                return h('div');
            },
        });
        const migrated = compile(script, [extension]);
        const parent = render({
            render() {
                return h(migrated, { ref: 'child' });
            },
        });
        const child = (parent.vm.$refs as any).child;
        expect(typeof (baseline.vm as any).extensionRead).toBe('function');
        expect(child.extensionValue).toBe((baseline.vm as any).extensionValue);
        expect(child.extensionDouble).toBe((baseline.vm as any).extensionDouble);
        expect(child.extensionRead()).toBe((baseline.vm as any).extensionRead());
    });

    it('keeps function-valued data and $data synchronized after legacy assignment', async () => {
        const extension: ComponentConfig = {
            methods: {
                replace() {
                    this.callback = () => 'next';
                },
            },
        };
        const baseline = render({
            data: () => ({ callback: () => 'base' }),
            mixins: [extension],
            render() {
                return h('div');
            },
        });
        const migrated = render(
            compile(
                `import { ref } from 'vue'; const callback = ref(() => 'base'); swDefinePublic({ callback });`,
                [extension],
                '<div>{{ callback() }}</div>',
            ),
        );
        (baseline.vm as any).replace();
        (migrated.vm.$.proxy as any).replace();
        await nextTick();
        expect(migrated.text()).toBe('next');
        expect((migrated.vm.$.proxy as any).$data.callback()).toBe((baseline.vm as any).$data.callback());
    });

    it('keeps public members accessible to children using this.$parent', () => {
        const child: ComponentConfig = {
            render() {
                return h('div', this.$parent.extensionRead());
            },
        };
        const extension: ComponentConfig = {
            methods: {
                extensionRead() {
                    return 'plugin';
                },
            },
            render() {
                return h(child);
            },
        };
        const baseline = render({ data: () => ({ count: 1 }), mixins: [extension] });
        const migrated = render(compile(script, [extension]));
        expect(migrated.text()).toBe(baseline.text());
    });
    it('exposes a retained legacy alias through parent refs when metadata names the new binding', () => {
        const migrated = compile(
            `
            import { ref } from 'vue';
            defineOptions({ legacyOptionsBindings: { oldCount: 'count' }, legacyOptionsMembers: { oldCount: 'data' } });
            const count = ref(1);
            swDefinePublic({ count });
        `,
            [],
        );
        const parent = render({
            render() {
                return h(migrated, { ref: 'child' });
            },
        });
        expect((parent.vm.$refs as any).child.oldCount).toBe(1);
    });

    it('preserves an explicitly exposed old-name binding for parent refs and writes', async () => {
        const migrated = compile(
            `
            import { ref } from 'vue';
            defineOptions({ legacyOptionsBindings: { oldCount: 'count' }, legacyOptionsMembers: { oldCount: 'data' } });
            const count = ref(1);
            const oldCount = count;
            swDefinePublic({ oldCount });
        `,
            [{ data: () => ({ oldCount: 3 }) }],
        );
        const parent = render({
            render() {
                return h(migrated, { ref: 'child' });
            },
        });
        const child = (parent.vm.$refs as any).child;
        expect(child.oldCount).toBe(3);
        child.oldCount = 5;
        await nextTick();
        expect(parent.text()).toBe('5');
        expect(child.oldCount).toBe(5);
    });

    it('exposes hook-created fields and writable legacy data and computed through parent refs', async () => {
        const migrated = compile(script, [
            {
                data: () => ({ extra: 2 }),
                created() {
                    this.hookHandle = 'created';
                },
                computed: {
                    extraDouble: {
                        get(this: { extra: number }): number {
                            return this.extra * 2;
                        },
                        set(this: { extra: number }, value: number) {
                            this.extra = value / 2;
                        },
                    },
                },
            },
        ]);
        const parent = render({
            render() {
                return h(migrated, { ref: 'child' });
            },
        });
        const child = (parent.vm.$refs as any).child;
        expect(child.hookHandle).toBe('created');
        expect(child.extraDouble).toBe(4);
        child.extra = 3;
        await nextTick();
        expect(child.extraDouble).toBe(6);
        child.extraDouble = 10;
        await nextTick();
        expect(child.extra).toBe(5);
        expect(child.$data.extra).toBe(5);
    });

    it('preserves native identity between hooks, ordinary methods and computed vm arguments', () => {
        const values = new WeakMap<object, string>();
        const createdReceivers: object[] = [];
        const extension: ComponentConfig = {
            created(this: object) {
                createdReceivers.push(this);
                values.set(this, 'registered');
            },
            methods: {
                readReceiver(this: object) {
                    return values.get(this);
                },
                returnReceiver(this: object) {
                    return this;
                },
            },
            computed: {
                computedReceiver(vm: object) {
                    return values.get(vm);
                },
            },
        };
        const migrated = render(compile(script, [extension]));
        const vm = migrated.vm.$.proxy as any;
        expect(vm.readReceiver()).toBe('registered');
        expect(vm.computedReceiver).toBe('registered');
        expect(vm.returnReceiver()).toBe(createdReceivers[0]);
    });

    it('calls ordinary $super predecessors with the same receiver as lifecycle hooks', async () => {
        const values = new WeakMap<object, string>();
        const migrated = render(compile(script, [
            {
                    created(this: object) {
                    values.set(this, 'registered');
                },
                methods: {
                        read(this: object) {
                        return values.get(this);
                    },
                },
                computed: {
                    receiver(vm: object) {
                        return values.get(vm);
                    },
                },
            },
            {
                methods: {
                    async read() {
                        await Promise.resolve();
                        return this.$super('read');
                    },
                },
                computed: {
                    receiver() {
                        return this.$super('receiver');
                    },
                },
            },
        ]));
        const vm = migrated.vm.$.proxy as any;
        expect(await vm.read()).toBe('registered');
        expect(vm.receiver).toBe('registered');
    });

    it('keeps private setup bindings hidden while exposing Options additions', () => {
        const migrated = compile(
            `
            import { ref } from 'vue';
            const count = ref(1);
            const privateValue = ref(2);
            function privateRead() { return privateValue.value; }
            swDefinePublic({ count });
        `,
            [{ data: () => ({ extra: 3 }) }],
            '<div>{{ count }}|{{ privateRead() }}</div>',
        );
        const parent = render({
            render() {
                return h(migrated, { ref: 'child' });
            },
        });
        const child = (parent.vm.$refs as any).child;
        expect(parent.text()).toBe('1|2');
        expect(child.extra).toBe(3);
        expect(child.privateValue).toBeUndefined();
        expect(child.privateRead).toBeUndefined();
        expect('privateValue' in child).toBe(false);
        expect(Object.keys(child as object)).not.toContain('privateRead');
    });
});
