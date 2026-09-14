/**
 * @sw-package framework
 */
import { mount, type VueWrapper } from '@vue/test-utils';
import { defineComponent, ref, h, nextTick, inject } from 'vue';
import { createLegacyComponent, prepareLegacyComponent } from '../options-composition-shim/component-definition';
import ComponentFactory, { type ComponentConfig } from 'src/core/factory/async-component.factory';
import { createExtendableSetup, _overridesMap } from './index';

type LegacyInstance = {
    $super: (name: string) => number;
    $emit: (...args: unknown[]) => void;
    added: string;
    pluginValue: () => string;
};

describe('legacy overrides through the factory registry', () => {
    const wrappers: VueWrapper[] = [];

    beforeEach(() => {
        ComponentFactory.getOverrideRegistry().clear();
        Object.keys(_overridesMap).forEach((key) => {
            delete _overridesMap[key];
        });
        jest.spyOn(console, 'warn').mockImplementation(() => {});
    });

    afterEach(() => {
        wrappers.forEach((wrapper) => wrapper.unmount());
        wrappers.length = 0;
        jest.restoreAllMocks();
    });

    function prepared(name: string, definition: object) {
        return prepareLegacyComponent(
            name,
            definition as ComponentConfig,
            ComponentFactory.getOverrideRegistry().get(name) ?? [],
        );
    }

    function base(name: string, async = false) {
        const definition = defineComponent({
            template: '<div>{{ value() }}|{{ added }}</div>',
            setup: (props, context) =>
                createExtendableSetup({ name, props, context }, () => ({
                    public: { value: () => 1 },
                })),
        });
        return async ? createLegacyComponent(definition as unknown as ComponentConfig, name) : prepared(name, definition);
    }

    it('applies synchronous overrides before first render and only once per instance', async () => {
        const created = jest.fn();
        const cleanup = jest.fn();
        ComponentFactory.override('legacy-mount', {
            data() {
                return { added: 'plugin' };
            },
            created(this: LegacyInstance) {
                created(typeof this.$emit);
            },
            beforeUnmount: cleanup,
            methods: {
                value(this: LegacyInstance) {
                    return this.$super('value') + 1;
                },
            },
        });

        const first = mount(base('legacy-mount'));
        const second = mount(base('legacy-mount'));
        wrappers.push(first, second);
        expect(first.text()).toBe('2|plugin');
        expect(second.text()).toBe('2|plugin');
        await flushPromises();
        expect(first.text()).toBe('2|plugin');
        expect(created.mock.calls).toEqual([
            ['function'],
            ['function'],
        ]);
        first.unmount();
        second.unmount();
        expect(cleanup).toHaveBeenCalledTimes(2);
    });

    it('lets later overrides call earlier plugin methods and earlier methods see final state', () => {
        ComponentFactory.override('legacy-chain', {
            data() {
                return { added: 'first' };
            },
            methods: {
                pluginValue(this: LegacyInstance) {
                    return this.added;
                },
                value(this: LegacyInstance) {
                    return this.pluginValue();
                },
            },
        });
        ComponentFactory.override('legacy-chain', {
            data() {
                return { added: 'second' };
            },
            methods: {
                pluginValue(this: LegacyInstance) {
                    return `${this.$super('pluginValue')}!`;
                },
            },
        });
        const wrapper = mount(base('legacy-chain'));
        wrappers.push(wrapper);
        expect(wrapper.text()).toBe('second!|second');
    });

    it('resolves async registrations in order before initializing the component', async () => {
        let resolveFirst!: (value: object) => void;
        const pending = new Promise<object>((resolve) => {
            resolveFirst = resolve;
        });
        ComponentFactory.override('legacy-async', async () => pending);
        ComponentFactory.override('legacy-async', {
            data() {
                return { added: 'second' };
            },
            methods: {
                value(this: LegacyInstance) {
                    return this.$super('value') + 10;
                },
            },
        });
        const pendingComponent = base('legacy-async', true);
        const wrapper = mount(defineComponent({ render: () => h(pendingComponent) }));
        wrappers.push(wrapper);
        resolveFirst({
            methods: {
                value(this: LegacyInstance) {
                    return this.$super('value') + 1;
                },
            },
        });
        await flushPromises();
        expect(wrapper.text()).toBe('12|second');
    });

    it('runs hooks and injection with their owner and stops watchers on unmount', async () => {
        const changes = jest.fn();
        const cleanup = jest.fn();
        const count = ref(0);
        ComponentFactory.override('legacy-late', {
            inject: ['count'],
            data() {
                return { added: 'late' };
            },
            watch: { count: changes },
            beforeUnmount: cleanup,
        });
        const wrapper = mount(base('legacy-late'), { global: { provide: { count } } });
        wrappers.push(wrapper);
        await flushPromises();
        count.value++;
        await nextTick();
        expect(changes).toHaveBeenCalledTimes(1);
        wrapper.unmount();
        count.value++;
        await nextTick();
        expect(changes).toHaveBeenCalledTimes(1);
        expect(cleanup).toHaveBeenCalledTimes(1);
    });

    it('passes errorCaptured arguments and honors false', () => {
        const error = new Error('child');
        const hook = jest.fn<boolean, [unknown]>(() => false);
        const applicationError = jest.fn();
        ComponentFactory.override('legacy-errors', { errorCaptured: hook });
        const child = defineComponent({
            render() {
                throw error;
            },
        });
        const parent = defineComponent({
            setup: (props, context) =>
                createExtendableSetup({ name: 'legacy-errors', props, context }, () => ({ public: {} })),
            render: () => h(child),
        });
        wrappers.push(mount(prepared('legacy-errors', parent), { global: { config: { errorHandler: applicationError } } }));
        expect(hook.mock.calls[0][0]).toBe(error);
        expect(applicationError).not.toHaveBeenCalled();
    });
    it('unwraps symbol-keyed injections, calls defaults with this, and provides to descendants without changing siblings', async () => {
        const token = Symbol('count');
        const count = ref(2);
        ComponentFactory.override('legacy-provider', {
            inject: {
                count: { from: token },
                fallback: {
                    from: 'missing',
                    default(this: { title: string }) {
                        return this.title;
                    },
                },
            },
            data(this: object) {
                const instance = this as { count: number; fallback: string };
                return { added: `${instance.count}:${instance.fallback}` };
            },
            methods: {
                increment(this: { count: number }) {
                    this.count++;
                },
            },
            provide(this: { increment: () => void }) {
                return { increment: this.increment };
            },
        });
        const child = defineComponent({
            setup() {
                const increment = inject<() => void>('increment');
                return () => h('button', { onClick: increment }, String(count.value));
            },
        });
        const parent = defineComponent({
            props: ['title'],
            setup(props, context) {
                createExtendableSetup({ name: 'legacy-provider', props, context }, () => ({ public: {} }));
                return () => h(child);
            },
        });
        const wrapper = mount(prepared('legacy-provider', parent), {
            props: { title: 'default' },
            global: { provide: { [token]: count } },
        });
        wrappers.push(wrapper);
        await wrapper.get('button').trigger('click');
        expect(count.value).toBe(3);
        expect(wrapper.text()).toBe('3');
        expect(wrapper.vm.$.appContext.provides).not.toHaveProperty('increment');
    });

    it('disposes imperative $watch calls made from events and forwards asynchronous created errors', async () => {
        const changes = jest.fn();
        const errors = jest.fn<void, [unknown]>();
        const failure = new Error('async created');
        ComponentFactory.override('legacy-imperative', {
            data: () => ({ added: 'initial' }),
            created() {
                return Promise.reject(failure);
            },
            methods: {
                start(this: { $watch: (path: string, callback: () => void) => void }) {
                    this.$watch('added', changes);
                },
                change(this: { added: string }) {
                    this.added += '!';
                },
            },
        });
        const component = base('legacy-imperative');
        const wrapper = mount(component, { global: { config: { errorHandler: errors } } });
        wrappers.push(wrapper);
        await flushPromises();
        const instance = wrapper.vm as unknown as {
            start: () => void;
            change: () => void;
            $data: object;
            $options: { methods: object };
        };
        instance.start();
        instance.change();
        await nextTick();
        expect(changes).toHaveBeenCalledTimes(1);
        expect(errors.mock.calls[0][0]).toBe(failure);
        wrapper.unmount();
        instance.change();
        await nextTick();
        expect(changes).toHaveBeenCalledTimes(1);
    });
    it('allows an earlier method to call a new method introduced by a later override', () => {
        ComponentFactory.override('legacy-added-method', {
            methods: {
                value(this: { laterValue: () => string }) {
                    return this.laterValue();
                },
            },
        });
        ComponentFactory.override('legacy-added-method', {
            methods: { laterValue: () => 'later' },
            data: () => ({ added: 'ready' }),
        });
        const wrapper = mount(base('legacy-added-method'));
        wrappers.push(wrapper);
        expect(wrapper.text()).toBe('later|ready');
    });
});
