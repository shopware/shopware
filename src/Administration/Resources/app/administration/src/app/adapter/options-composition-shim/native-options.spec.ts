/** @sw-package framework */
/* eslint-disable @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-call, @typescript-eslint/no-unsafe-return, @typescript-eslint/no-unsafe-argument */
import { mount, type VueWrapper } from '@vue/test-utils';
import { computed, defineComponent, h, nextTick, ref, type SetupContext } from 'vue';
import type { ComponentConfig, IndexedAwaitedComponentConfig } from 'src/core/factory/async-component.factory';
import { createExtendableSetup, _overridesMap } from '../composition-extension-system';
import { prepareLegacyComponent } from './component-definition';

/** Mount the same resolved definition used by the factory and direct SFC imports. */
function component(setup: () => Record<string, unknown>, overrides: ComponentConfig[], base: ComponentConfig = {}) {
    return prepareLegacyComponent(
        'native-options-test',
        {
            __swExtendable: true,
            render(this: any) {
                return h('div', `${this.count}|${this.label}`);
            },
            setup(props: Record<string, unknown>, context: SetupContext) {
                return createExtendableSetup({ name: 'native-options-test', props, context }, () => ({ public: setup() }));
            },
            ...base,
        },
        overrides.map((resolvedConfig) => ({ resolvedConfig }) as IndexedAwaitedComponentConfig),
    );
}

describe('Vue Options on an SFC state bridge', () => {
    const wrappers: VueWrapper[] = [];
    function render(definition: ComponentConfig, options = {}) {
        const wrapper = mount(definition, options);
        wrappers.push(wrapper);
        return wrapper;
    }
    beforeEach(() => {
        Object.keys(_overridesMap).forEach((key) => {
            delete _overridesMap[key];
        });
    });
    afterEach(() => {
        wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
        jest.restoreAllMocks();
    });

    it('lets Vue initialize injections, methods, data, computed, immediate watchers and created in order', () => {
        const events: unknown[] = [];
        const definition = component(
            () => ({ count: ref(1), label: ref('base') }),
            [
                {
                    inject: ['prefix'],
                    beforeCreate() {
                        events.push('beforeCreate');
                    },
                    methods: {
                        format(this: any, value: string) {
                            return `${this.prefix}:${value}`;
                        },
                    },
                    data(this: any, vm: any) {
                        events.push([
                            'data',
                            vm === this,
                        ]);
                        return { count: 4, label: this.format('plugin') };
                    },
                    computed: {
                        doubled(this: any) {
                            return this.count * 2;
                        },
                    },
                    watch: {
                        count: {
                            immediate: true,
                            handler(this: any, value: number) {
                                events.push([
                                    'watch',
                                    value,
                                    this.doubled,
                                    this.label,
                                ]);
                            },
                        },
                    },
                    created(this: any) {
                        events.push([
                            'created',
                            this.$data.count,
                            this.doubled,
                        ]);
                    },
                },
            ],
        );
        const wrapper = render(definition, { global: { provide: { prefix: 'injected' } } });
        expect(wrapper.text()).toBe('4|injected:plugin');
        expect(events).toEqual([
            'beforeCreate',
            [
                'data',
                true,
            ],
            [
                'watch',
                4,
                8,
                'injected:plugin',
            ],
            [
                'created',
                4,
                8,
            ],
        ]);
    });

    it('preserves the computed vm argument through native reads and $super', () => {
        const definition = component(
            () => ({ count: ref(2), label: ref('base') }),
            [
                {
                    computed: {
                        doubled(this: any, vm: any) {
                            expect(vm).toBe(this);
                            return vm.count * 2;
                        },
                    },
                },
                {
                    computed: {
                        doubled(this: any) {
                            return this.$super('doubled.get') + 1;
                        },
                    },
                },
            ],
        );
        expect((render(definition).vm as any).doubled).toBe(5);
    });

    it('shares original refs, supports replacement object shapes, and isolates component instances', async () => {
        const originals: ReturnType<typeof ref>[] = [];
        const definition = component(() => {
            const count = ref(1);
            originals.push(count);
            return { count, label: ref('base'), order: ref({ customer: { name: 'first' } }) };
        }, [{ data: () => ({ order: { total: 7 } }) }]);
        const first = render(definition);
        const second = render(definition);
        const vm = first.vm as any;
        expect(vm.order).toEqual({ total: 7 });
        vm.count = 6;
        await nextTick();
        expect(originals[0].value).toBe(6);
        expect(first.text()).toBe('6|base');
        expect(second.text()).toBe('1|base');
    });

    it('keeps multi-layer method and computed $super calls, including after await', async () => {
        const definition = component(() => {
            const count = ref(1);
            return {
                count,
                label: computed({
                    get: () => `base:${count.value}`,
                    set: (value: string) => {
                        count.value = Number(value);
                    },
                }),
                read: () => 'base',
            };
        }, [
            {
                methods: {
                    async read(this: any) {
                        await Promise.resolve();
                        return `${await this.$super('read')}:first`;
                    },
                },
                computed: {
                    label: {
                        get(this: any) {
                            return `${this.$super('label.get')}:first`;
                        },
                        set(this: any, value: string) {
                            this.$super('label.set', value);
                        },
                    },
                },
            },
            {
                methods: {
                    async read(this: any) {
                        return `${await this.$super('read')}:second`;
                    },
                },
                computed: {
                    label(this: any) {
                        return `${this.$super('label')}:second`;
                    },
                },
            },
            {
                computed: {
                    label: {
                        get(this: any) {
                            return `${this.$super('label.get')}:third`;
                        },
                    },
                },
            },
        ]);
        const vm = render(definition).vm as any;
        expect(await vm.read()).toBe('base:first:second');
        expect(vm.label).toBe('base:1:first:second:third');
        const firstLayer = render(
            component(() => {
                const count = ref(1);
                return {
                    count,
                    label: computed({
                        get: () => String(count.value),
                        set: (value: string) => {
                            count.value = Number(value);
                        },
                    }),
                };
            }, [
                {
                    computed: {
                        label: {
                            get(this: any) {
                                return this.$super('label.get');
                            },
                            set(this: any, value: string) {
                                this.$super('label.set', value);
                            },
                        },
                    },
                },
            ]),
        ).vm as any;
        firstLayer.label = '8';
        expect(firstLayer.count).toBe(8);
    });

    it('uses registered string mixins, nested inheritance, native merge strategies and hook deduplication', () => {
        const hook = jest.fn();
        const mixin = {
            methods: {
                read() {
                    return 'mixin';
                },
            },
            created: hook,
            pluginMetadata: ['mixin'],
        };
        jest.spyOn(Shopware.Mixin, 'getByName').mockReturnValue(mixin as any);
        const wrapper = render(
            component(
                () => ({ count: ref(1), label: ref('base') }),
                [
                    {
                        extends: { created: hook, pluginMetadata: ['parent'] },
                        mixins: ['registered-mixin'],
                        created: hook,
                        pluginMetadata: ['own'],
                        methods: {
                            read(this: any) {
                                return `${this.$super('read')}:own`;
                            },
                        },
                    } as ComponentConfig,
                ],
            ),
            {
                global: {
                    config: {
                        optionMergeStrategies: {
                            pluginMetadata: (before: string[] = [], after: string[] = []) => [
                                ...before,
                                ...after,
                            ],
                        },
                    },
                },
            },
        );
        const vm = wrapper.vm as any;
        expect(vm.read()).toBe('mixin:own');
        expect(vm.$options.pluginMetadata).toEqual([
            'parent',
            'mixin',
            'own',
        ]);
        expect(hook).toHaveBeenCalledTimes(1);
        expect(Shopware.Mixin.getByName).toHaveBeenCalledWith('registered-mixin');
    });

    it('retains route guards, shortcut declarations and title metadata in the merged options', () => {
        const beforeRouteLeave = jest.fn();
        const metaInfo = jest.fn();
        const shortcuts = { 'SYSTEMKEY+S': 'save' };
        const vm = render(
            component(
                () => ({ count: ref(1), label: ref('base') }),
                [
                    {
                        beforeRouteLeave,
                        metaInfo,
                        shortcuts,
                    } as ComponentConfig,
                ],
            ),
        ).vm as any;
        expect(vm.$options.beforeRouteLeave).toBe(beforeRouteLeave);
        expect(vm.$options.metaInfo).toBe(metaInfo);
        expect(vm.$options.shortcuts).toBe(shortcuts);
    });

    it('uses native watcher arrays, string handlers, paths and cleanup on replacement and unmount', async () => {
        const calls: unknown[] = [];
        const cleanup = jest.fn();
        const wrapper = render(
            component(
                () => ({ count: ref(1), label: ref('base'), order: ref({}) }),
                [
                    {
                        methods: {
                            changed(value: unknown, _previous: unknown, onCleanup: (fn: () => void) => void) {
                                calls.push(value);
                                onCleanup(cleanup);
                            },
                        },
                        watch: {
                            'order.customer.name': [{ handler: 'changed', immediate: true }],
                            count: {
                                once: true,
                                handler(value: number) {
                                    calls.push(value);
                                },
                            },
                        },
                    },
                ],
            ),
        );
        const vm = wrapper.vm as any;
        vm.order = { customer: { name: 'next' } };
        vm.count++;
        await nextTick();
        vm.count++;
        await nextTick();
        expect(calls).toEqual([
            undefined,
            'next',
            2,
        ]);
        expect(cleanup).toHaveBeenCalledTimes(1);
        wrapper.unmount();
        expect(cleanup).toHaveBeenCalledTimes(2);
    });

    it('provides values from initialized Options and unwraps injected refs for child Options', () => {
        const provided = ref('value');
        const child = defineComponent({
            inject: ['shared'],
            render(this: any) {
                return h('b', this.shared);
            },
        });
        const wrapper = render(
            component(
                () => ({ count: ref(1), label: ref('base') }),
                [
                    {
                        provide() {
                            return { shared: provided };
                        },
                        render() {
                            return h(child);
                        },
                    },
                ],
            ),
        );
        expect(wrapper.get('b').text()).toBe('value');
        expect((wrapper.getComponent(child).vm as any).shared).toBe('value');
    });
});
