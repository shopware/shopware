/**
 * @sw-package framework
 */

/* eslint-disable @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-assignment, max-len, @typescript-eslint/no-unsafe-call, @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-return, @typescript-eslint/no-unsafe-argument, @typescript-eslint/no-unused-vars */

import { createExtendableSetup, _overridesMap } from 'src/app/adapter/composition-extension-system';
import {
    shouldActivateShim,
    convertOptionsApiOverrideToCompositionApi,
    _compositionApiComponents,
} from 'src/app/adapter/options-composition-shim';
import { mount } from '@vue/test-utils';
import { ref, computed, defineComponent, nextTick, reactive } from 'vue';

/**
 * Helper: wraps convertOptionsApiOverrideToCompositionApi and silences the
 * deprecation console.warn that fires on every call.
 */
function convertWithSilencedWarning(
    componentName: string,
    config: Parameters<typeof convertOptionsApiOverrideToCompositionApi>[1],
) {
    const spy = jest.spyOn(console, 'warn').mockImplementation(() => {});
    const result = convertOptionsApiOverrideToCompositionApi(componentName, config);
    spy.mockRestore();
    return result;
}

describe('src/app/adapter/options-composition-shim', () => {
    beforeEach(() => {
        const entries = [...Object.keys(_overridesMap)];
        entries.forEach((key) => {
            delete _overridesMap[key];
        });

        _compositionApiComponents.clear();
        jest.clearAllMocks();
    });

    describe('shouldActivateShim():', () => {
        it('should return true when target uses Composition API and override has methods', () => {
            _compositionApiComponents.add('sw-example');

            const result = shouldActivateShim('sw-example', {
                methods: { save() {} },
            });

            expect(result).toBe(true);
        });

        it('should return true when target uses Composition API and override has computed', () => {
            _compositionApiComponents.add('sw-example');

            const result = shouldActivateShim('sw-example', {
                computed: {
                    fullName() {
                        return '';
                    },
                },
            });

            expect(result).toBe(true);
        });

        it('should return true when target uses Composition API and override has data', () => {
            _compositionApiComponents.add('sw-example');

            const result = shouldActivateShim('sw-example', {
                data() {
                    return { count: 0 };
                },
            });

            expect(result).toBe(true);
        });

        it('should return true when target uses Composition API and override has watch', () => {
            _compositionApiComponents.add('sw-example');

            const result = shouldActivateShim('sw-example', {
                watch: { count() {} },
            });

            expect(result).toBe(true);
        });

        it('should return true when target uses Composition API and override has inject', () => {
            _compositionApiComponents.add('sw-example');

            const result = shouldActivateShim('sw-example', {
                inject: ['repositoryFactory'],
            });

            expect(result).toBe(true);
        });

        it('should return true when target uses Composition API and override has mixins', () => {
            _compositionApiComponents.add('sw-example');

            const result = shouldActivateShim('sw-example', {
                mixins: [{ methods: { foo() {} } }],
            });

            expect(result).toBe(true);
        });

        it('should return true when target uses Composition API and override has lifecycle hooks', () => {
            _compositionApiComponents.add('sw-example');

            const result = shouldActivateShim('sw-example', {
                mounted() {},
            });

            expect(result).toBe(true);
        });

        it('should return true when target uses Composition API and mixin has lifecycle hooks', () => {
            _compositionApiComponents.add('sw-example');

            const result = shouldActivateShim('sw-example', {
                mixins: [{ created() {} }],
            });

            expect(result).toBe(true);
        });

        it('should return false when target does NOT use Composition API', () => {
            const result = shouldActivateShim('sw-legacy', {
                methods: { save() {} },
            });

            expect(result).toBe(false);
        });

        it('should return false when override has no Options API patterns', () => {
            _compositionApiComponents.add('sw-example');

            const result = shouldActivateShim('sw-example', {
                name: 'sw-example',
            });

            expect(result).toBe(false);
        });

        it('should return false when neither condition is met', () => {
            const result = shouldActivateShim('sw-unknown', {
                name: 'sw-unknown',
            });

            expect(result).toBe(false);
        });
    });

    describe('convertData():', () => {
        it('should convert data() overriding an existing ref value', async () => {
            _compositionApiComponents.add('originalComponent');

            const originalComponent = defineComponent({
                template: '<div><span class="msg">{{ message }}</span></div>',
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        const message = ref('original');

                        return {
                            public: { message },
                        };
                    }),
            });

            const wrapper = mount(originalComponent);
            expect(wrapper.find('.msg').text()).toBe('original');

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                data() {
                    return { message: 'overridden' };
                },
            });

            _overridesMap.originalComponent.push(overrideFn);

            await flushPromises();

            expect(wrapper.find('.msg').text()).toBe('overridden');
        });

        it('should convert data() return values to refs', () => {
            _compositionApiComponents.add('originalComponent');

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                data() {
                    return { count: 42, name: 'test' };
                },
            });

            const result = overrideFn({}, {});

            expect(result.count.value).toBe(42);
            expect(result.name.value).toBe('test');
        });
    });

    describe('convertMethods():', () => {
        it('should convert methods and bind this to proxy', async () => {
            _compositionApiComponents.add('originalComponent');

            const originalComponent = defineComponent({
                template: `
                    <div class="count">Count: {{ count }}</div>
                    <button @click="increment">Increment</button>
                `,
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        const count = ref(1);
                        const increment = () => {
                            count.value += 1;
                        };

                        return {
                            public: { count, increment },
                        };
                    }),
            });

            const wrapper = mount(originalComponent);
            expect(wrapper.find('.count').text()).toBe('Count: 1');

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                methods: {
                    increment() {
                        this.count += 10;
                    },
                },
            });

            _overridesMap.originalComponent.push(overrideFn);

            await flushPromises();

            await wrapper.find('button').trigger('click');
            expect(wrapper.find('.count').text()).toBe('Count: 11');
        });

        it('should support this.$super() to call previous method', async () => {
            _compositionApiComponents.add('originalComponent');

            const originalComponent = defineComponent({
                template: `
                    <div class="count">Count: {{ count }}</div>
                    <button @click="increment">Increment</button>
                `,
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        const count = ref(1);
                        const increment = () => {
                            count.value += 1;
                        };

                        return {
                            public: { count, increment },
                        };
                    }),
            });

            const wrapper = mount(originalComponent);
            expect(wrapper.find('.count').text()).toBe('Count: 1');

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                methods: {
                    increment() {
                        this.$super('increment');
                        this.count += 5;
                    },
                },
            });

            _overridesMap.originalComponent.push(overrideFn);

            await flushPromises();

            await wrapper.find('button').trigger('click');
            // Original increment (+1) + extra (+5) = 7
            expect(wrapper.find('.count').text()).toBe('Count: 7');
        });

        it('should throw error when $super references a non-existent method', () => {
            _compositionApiComponents.add('originalComponent');

            const previousState = {
                count: ref(1),
                increment: () => {},
            };

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                methods: {
                    doSomething() {
                        this.$super('nonExistentMethod');
                    },
                },
            });

            const result = overrideFn(previousState, {});

            expect(() => {
                result.doSomething();
            }).toThrow('$super: method "nonExistentMethod" not found in previous state');
        });
    });

    describe('convertComputed():', () => {
        it('should convert getter-only computed properties', async () => {
            _compositionApiComponents.add('originalComponent');

            const originalComponent = defineComponent({
                template: `
                    <div class="count">Count: {{ count }}</div>
                    <div class="doubled">Doubled: {{ doubled }}</div>
                `,
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        const count = ref(5);
                        const doubled = computed(() => count.value * 2);

                        return {
                            public: { count, doubled },
                        };
                    }),
            });

            const wrapper = mount(originalComponent);
            expect(wrapper.find('.doubled').text()).toBe('Doubled: 10');

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                computed: {
                    doubled() {
                        return this.count * 3;
                    },
                },
            });

            _overridesMap.originalComponent.push(overrideFn);

            await flushPromises();

            expect(wrapper.find('.doubled').text()).toBe('Doubled: 15');
        });

        it('should convert getter/setter computed properties', async () => {
            _compositionApiComponents.add('originalComponent');

            const originalComponent = defineComponent({
                template: `
                    <div class="count">Count: {{ count }}</div>
                    <div class="doubled">Doubled: {{ doubled }}</div>
                `,
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        const count = ref(5);
                        const doubled = computed({
                            get: () => count.value * 2,
                            set: (val: number) => {
                                count.value = val / 2;
                            },
                        });

                        return {
                            public: { count, doubled },
                        };
                    }),
            });

            const wrapper = mount(originalComponent);
            expect(wrapper.find('.doubled').text()).toBe('Doubled: 10');

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                computed: {
                    doubled: {
                        get(): number {
                            return (this as any).count * 4;
                        },
                        set(val: number) {
                            (this as any).count = val / 4;
                        },
                    },
                },
            });

            _overridesMap.originalComponent.push(overrideFn);

            await flushPromises();

            // getter: 5 * 4 = 20
            expect(wrapper.find('.doubled').text()).toBe('Doubled: 20');
        });

        it('should allow computed to access previousState values via this', async () => {
            _compositionApiComponents.add('originalComponent');

            const originalComponent = defineComponent({
                template: `
                    <div class="name">Name: {{ name }}</div>
                    <div class="greeting">Greeting: {{ greeting }}</div>
                `,
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        const name = ref('World');
                        const greeting = computed(() => `Hello ${name.value}`);

                        return {
                            public: { name, greeting },
                        };
                    }),
            });

            const wrapper = mount(originalComponent);
            expect(wrapper.find('.greeting').text()).toBe('Greeting: Hello World');

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                computed: {
                    greeting() {
                        return `Goodbye ${this.name}`;
                    },
                },
            });

            _overridesMap.originalComponent.push(overrideFn);

            await flushPromises();

            expect(wrapper.find('.greeting').text()).toBe('Greeting: Goodbye World');
        });
    });

    describe('setupWatchers():', () => {
        it('should convert function watchers', async () => {
            _compositionApiComponents.add('originalComponent');

            const watchCallback = jest.fn();

            const originalComponent = defineComponent({
                template: '<div class="count">Count: {{ count }}</div>',
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        const count = ref(1);

                        return {
                            public: { count },
                        };
                    }),
            });

            mount(originalComponent);

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                watch: {
                    count(newVal: number, oldVal: number) {
                        watchCallback(newVal, oldVal);
                    },
                },
            });

            _overridesMap.originalComponent.push(overrideFn);

            await flushPromises();

            // Trigger a change on count via another override
            _overridesMap.originalComponent.push((previousState: any) => {
                previousState.count.value = 42;
                return {};
            });

            await flushPromises();
            await nextTick();

            expect(watchCallback).toHaveBeenCalled();
        });

        it('should convert object watchers with immediate option', async () => {
            _compositionApiComponents.add('originalComponent');

            const watchCallback = jest.fn();

            const originalComponent = defineComponent({
                template: '<div class="count">Count: {{ count }}</div>',
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        const count = ref(1);

                        return {
                            public: { count },
                        };
                    }),
            });

            mount(originalComponent);

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                watch: {
                    count: {
                        handler(newVal: number, oldVal: number) {
                            watchCallback(newVal, oldVal);
                        },
                        immediate: true,
                    },
                },
            });

            _overridesMap.originalComponent.push(overrideFn);

            await flushPromises();
            await nextTick();

            expect(watchCallback).toHaveBeenCalled();
        });

        it('should convert string method name watchers', async () => {
            _compositionApiComponents.add('originalComponent');

            const methodCallback = jest.fn();

            const originalComponent = defineComponent({
                template: '<div class="count">Count: {{ count }}</div>',
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        const count = ref(1);

                        return {
                            public: { count },
                        };
                    }),
            });

            mount(originalComponent);

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                methods: {
                    onCountChange(newVal: number, oldVal: number) {
                        methodCallback(newVal, oldVal);
                    },
                },
                watch: {
                    count: 'onCountChange',
                },
            });

            _overridesMap.originalComponent.push(overrideFn);

            await flushPromises();

            // Trigger a change
            _overridesMap.originalComponent.push((previousState: any) => {
                previousState.count.value = 99;
                return {};
            });

            await flushPromises();
            await nextTick();

            expect(methodCallback).toHaveBeenCalled();
        });
    });

    describe('createThisProxy():', () => {
        it('should resolve this.propertyName to previousState ref values', () => {
            _compositionApiComponents.add('originalComponent');

            const previousState = {
                count: ref(42),
                name: ref('test'),
            };

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                methods: {
                    getCount() {
                        return this.count;
                    },
                    getName() {
                        return this.name;
                    },
                },
            });

            const result = overrideFn(previousState, {});

            expect(result.getCount()).toBe(42);
            expect(result.getName()).toBe('test');
        });

        it('should allow setting ref values via this.propertyName', () => {
            _compositionApiComponents.add('originalComponent');

            const previousState = {
                count: ref(1),
            };

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                methods: {
                    setCount() {
                        this.count = 100;
                    },
                },
            });

            const result = overrideFn(previousState, {});
            result.setCount();

            expect(previousState.count.value).toBe(100);
        });

        it('should resolve props via this', () => {
            _compositionApiComponents.add('originalComponent');

            const previousState = {};
            const props = { title: 'Hello' };

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                methods: {
                    getTitle() {
                        return this.title;
                    },
                },
            });

            const result = overrideFn(previousState, props);

            expect(result.getTitle()).toBe('Hello');
        });

        it('should prioritize local state over previousState', () => {
            _compositionApiComponents.add('originalComponent');

            const previousState = {
                count: ref(1),
            };

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                data() {
                    return { count: 999 };
                },
                methods: {
                    getCount() {
                        return this.count;
                    },
                },
            });

            const result = overrideFn(previousState, {});

            expect(result.getCount()).toBe(999);
        });

        it('should warn about accessing undefined properties', () => {
            _compositionApiComponents.add('originalComponent');
            const consoleWarn = jest.spyOn(console, 'warn').mockImplementation(() => {});

            const previousState = {};

            const overrideFn = convertOptionsApiOverrideToCompositionApi('originalComponent', {
                methods: {
                    accessUndefined() {
                        return this.nonExistentProp;
                    },
                },
            });

            const result = overrideFn(previousState, {});
            const value = result.accessUndefined();

            expect(value).toBeUndefined();
            expect(consoleWarn).toHaveBeenCalledWith(expect.stringContaining('Property "nonExistentProp" not found'));

            consoleWarn.mockRestore();
        });

        it('should not warn about Vue internal properties starting with $ or _', () => {
            _compositionApiComponents.add('originalComponent');
            const consoleWarn = jest.spyOn(console, 'warn').mockImplementation(() => {});

            const previousState = {};

            const overrideFn = convertOptionsApiOverrideToCompositionApi('originalComponent', {
                methods: {
                    accessInternal() {
                        const a = this._internal;
                        const b = this.$route;
                        return [
                            a,
                            b,
                        ];
                    },
                },
            });

            const result = overrideFn(previousState, {});
            result.accessInternal();

            // Filter out the deprecation warning to check only property warnings
            const propertyWarnings = consoleWarn.mock.calls.filter(
                (call) => typeof call[0] === 'string' && call[0].includes('not found in component state'),
            );
            expect(propertyWarnings).toHaveLength(0);

            consoleWarn.mockRestore();
        });

        it('should error when setting a property not found in any state', () => {
            _compositionApiComponents.add('originalComponent');
            const consoleError = jest.spyOn(console, 'error').mockImplementation(() => {});
            const consoleWarn = jest.spyOn(console, 'warn').mockImplementation(() => {});

            const previousState = {};

            const overrideFn = convertOptionsApiOverrideToCompositionApi('originalComponent', {
                methods: {
                    setUnknown() {
                        try {
                            this.unknownProp = 123;
                        } catch {
                            // Proxy set returning false throws TypeError in strict mode
                        }
                    },
                },
            });

            const result = overrideFn(previousState, {});
            result.setUnknown();

            expect(consoleError).toHaveBeenCalledWith(expect.stringContaining('Cannot set property "unknownProp"'));

            consoleError.mockRestore();
            consoleWarn.mockRestore();
        });
    });

    describe('mergeMixins():', () => {
        it('should merge mixin methods into override config', async () => {
            _compositionApiComponents.add('originalComponent');

            const originalComponent = defineComponent({
                template: `
                    <div class="count">Count: {{ count }}</div>
                    <button class="increment" @click="increment">Increment</button>
                    <button class="decrement" @click="decrement">Decrement</button>
                `,
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        const count = ref(10);
                        const increment = () => {
                            count.value += 1;
                        };
                        const decrement = () => {
                            count.value -= 1;
                        };

                        return {
                            public: { count, increment, decrement },
                        };
                    }),
            });

            const wrapper = mount(originalComponent);
            expect(wrapper.find('.count').text()).toBe('Count: 10');

            const myMixin = {
                methods: {
                    decrement(this: any) {
                        this.count -= 5;
                    },
                },
            };

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                mixins: [myMixin],
                methods: {
                    increment() {
                        this.count += 5;
                    },
                },
            });

            _overridesMap.originalComponent.push(overrideFn);

            await flushPromises();

            await wrapper.find('.increment').trigger('click');
            expect(wrapper.find('.count').text()).toBe('Count: 15');

            await wrapper.find('.decrement').trigger('click');
            expect(wrapper.find('.count').text()).toBe('Count: 10');
        });

        it('should merge mixin data into override config', () => {
            _compositionApiComponents.add('originalComponent');

            const myMixin = {
                data() {
                    return { mixinValue: 'from-mixin' };
                },
            };

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                mixins: [myMixin],
                data() {
                    return { localValue: 'from-override' };
                },
            });

            const result = overrideFn({}, {});

            expect(result.mixinValue.value).toBe('from-mixin');
            expect(result.localValue.value).toBe('from-override');
        });

        it('should merge mixin lifecycle hooks and fire them', async () => {
            _compositionApiComponents.add('originalComponent');

            const createdCallback = jest.fn();

            const myMixin = {
                created() {
                    createdCallback();
                },
                methods: {
                    foo() {},
                },
            };

            const originalComponent = defineComponent({
                template: '<div class="count">{{ count }}</div>',
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        const count = ref(0);
                        return { public: { count } };
                    }),
            });

            mount(originalComponent);

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                mixins: [myMixin],
            });

            _overridesMap.originalComponent.push(overrideFn);

            await flushPromises();

            expect(createdCallback).toHaveBeenCalled();
        });
    });

    describe('setupLifecycleHooks():', () => {
        it('should fire created hook immediately during setup', async () => {
            _compositionApiComponents.add('originalComponent');

            const createdCallback = jest.fn();

            const originalComponent = defineComponent({
                template: '<div class="count">{{ count }}</div>',
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        const count = ref(0);
                        return { public: { count } };
                    }),
            });

            mount(originalComponent);

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                created() {
                    createdCallback();
                },
                methods: { noop() {} },
            });

            _overridesMap.originalComponent.push(overrideFn);

            await flushPromises();

            expect(createdCallback).toHaveBeenCalledTimes(1);
        });

        it('should fire beforeCreate hook immediately during setup', async () => {
            _compositionApiComponents.add('originalComponent');

            const beforeCreateCallback = jest.fn();

            const originalComponent = defineComponent({
                template: '<div class="count">{{ count }}</div>',
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        const count = ref(0);
                        return { public: { count } };
                    }),
            });

            mount(originalComponent);

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                beforeCreate() {
                    beforeCreateCallback();
                },
                methods: { noop() {} },
            });

            _overridesMap.originalComponent.push(overrideFn);

            await flushPromises();

            expect(beforeCreateCallback).toHaveBeenCalledTimes(1);
        });

        it('should fire mounted hook after component mounts', async () => {
            _compositionApiComponents.add('originalComponent');

            const mountedCallback = jest.fn();

            const originalComponent = defineComponent({
                template: '<div class="count">{{ count }}</div>',
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        const count = ref(0);
                        return { public: { count } };
                    }),
            });

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                mounted() {
                    mountedCallback();
                },
                methods: { noop() {} },
            });

            _overridesMap.originalComponent = reactive([]);
            _overridesMap.originalComponent.push(overrideFn);

            mount(originalComponent);

            await flushPromises();
            await nextTick();

            expect(mountedCallback).toHaveBeenCalledTimes(1);
        });

        it('should fire beforeUnmount and unmounted hooks on component destroy', async () => {
            _compositionApiComponents.add('originalComponent');

            const beforeUnmountCallback = jest.fn();
            const unmountedCallback = jest.fn();

            const originalComponent = defineComponent({
                template: '<div class="count">{{ count }}</div>',
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        const count = ref(0);
                        return { public: { count } };
                    }),
            });

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                beforeUnmount() {
                    beforeUnmountCallback();
                },
                unmounted() {
                    unmountedCallback();
                },
                methods: { noop() {} },
            });

            _overridesMap.originalComponent = reactive([]);
            _overridesMap.originalComponent.push(overrideFn);

            const wrapper = mount(originalComponent);

            await flushPromises();
            await nextTick();

            expect(beforeUnmountCallback).not.toHaveBeenCalled();
            expect(unmountedCallback).not.toHaveBeenCalled();

            wrapper.unmount();

            expect(beforeUnmountCallback).toHaveBeenCalledTimes(1);
            expect(unmountedCallback).toHaveBeenCalledTimes(1);
        });

        it('should provide correct this context inside lifecycle hooks', async () => {
            _compositionApiComponents.add('originalComponent');

            let capturedCount: number | undefined;

            const originalComponent = defineComponent({
                template: '<div class="count">{{ count }}</div>',
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        const count = ref(42);
                        return { public: { count } };
                    }),
            });

            mount(originalComponent);

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                created() {
                    capturedCount = this.count;
                },
                methods: { noop() {} },
            });

            _overridesMap.originalComponent.push(overrideFn);

            await flushPromises();

            expect(capturedCount).toBe(42);
        });

        it('should fire mixin hooks before component hooks (Vue merge order)', async () => {
            _compositionApiComponents.add('originalComponent');

            const callOrder: string[] = [];

            const myMixin = {
                created() {
                    callOrder.push('mixin-created');
                },
            };

            const originalComponent = defineComponent({
                template: '<div class="count">{{ count }}</div>',
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        const count = ref(0);
                        return { public: { count } };
                    }),
            });

            mount(originalComponent);

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                mixins: [myMixin],
                created() {
                    callOrder.push('component-created');
                },
            });

            _overridesMap.originalComponent.push(overrideFn);

            await flushPromises();

            expect(callOrder).toEqual([
                'mixin-created',
                'component-created',
            ]);
        });

        it('should fire hooks from multiple mixins in order', async () => {
            _compositionApiComponents.add('originalComponent');

            const callOrder: string[] = [];

            const mixinA = {
                created() {
                    callOrder.push('mixinA');
                },
            };
            const mixinB = {
                created() {
                    callOrder.push('mixinB');
                },
            };

            const originalComponent = defineComponent({
                template: '<div class="count">{{ count }}</div>',
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        const count = ref(0);
                        return { public: { count } };
                    }),
            });

            mount(originalComponent);

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                mixins: [mixinA, mixinB],
                created() {
                    callOrder.push('component');
                },
            });

            _overridesMap.originalComponent.push(overrideFn);

            await flushPromises();

            expect(callOrder).toEqual([
                'mixinA',
                'mixinB',
                'component',
            ]);
        });

        it('should work together with watch and data overrides', async () => {
            _compositionApiComponents.add('originalComponent');

            const createdCallback = jest.fn();
            const watchCallback = jest.fn();

            const originalComponent = defineComponent({
                template: '<div class="count">{{ count }}</div>',
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        const count = ref(0);
                        return { public: { count } };
                    }),
            });

            mount(originalComponent);

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                data() {
                    return { extra: 'test' };
                },
                created() {
                    createdCallback(this.extra);
                },
                watch: {
                    count(newVal: number) {
                        watchCallback(newVal);
                    },
                },
            });

            _overridesMap.originalComponent.push(overrideFn);

            await flushPromises();

            expect(createdCallback).toHaveBeenCalledWith('test');
        });

        it('should handle override with only lifecycle hooks (no methods/data)', async () => {
            _compositionApiComponents.add('originalComponent');

            const mountedCallback = jest.fn();

            const originalComponent = defineComponent({
                template: '<div class="count">{{ count }}</div>',
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        const count = ref(0);
                        return { public: { count } };
                    }),
            });

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                mounted() {
                    mountedCallback();
                },
            });

            _overridesMap.originalComponent = reactive([]);
            _overridesMap.originalComponent.push(overrideFn);

            mount(originalComponent);

            await flushPromises();
            await nextTick();

            expect(mountedCallback).toHaveBeenCalledTimes(1);
        });
    });

    describe('Unsupported features:', () => {
        it('should log error for custom render() functions', () => {
            _compositionApiComponents.add('originalComponent');
            const consoleError = jest.spyOn(console, 'error').mockImplementation(() => {});
            const consoleWarn = jest.spyOn(console, 'warn').mockImplementation(() => {});

            convertOptionsApiOverrideToCompositionApi('originalComponent', {
                render() {
                    return null;
                },
                methods: { foo() {} },
            });

            expect(consoleError).toHaveBeenCalledWith(
                expect.stringContaining('Custom render() functions are not supported'),
            );

            consoleError.mockRestore();
            consoleWarn.mockRestore();
        });
    });

    describe('Deprecation warning:', () => {
        it('should log deprecation warning when shim is activated', () => {
            _compositionApiComponents.add('originalComponent');
            const consoleWarn = jest.spyOn(console, 'warn').mockImplementation(() => {});

            convertOptionsApiOverrideToCompositionApi('originalComponent', {
                methods: { foo() {} },
            });

            expect(consoleWarn).toHaveBeenCalledWith(expect.stringContaining('[Deprecation Warning]'));
            expect(consoleWarn).toHaveBeenCalledWith(expect.stringContaining('originalComponent'));
            expect(consoleWarn).toHaveBeenCalledWith(expect.stringContaining('overrideComponentSetup()'));

            consoleWarn.mockRestore();
        });

        it('should include migration docs link in deprecation warning', () => {
            _compositionApiComponents.add('originalComponent');
            const consoleWarn = jest.spyOn(console, 'warn').mockImplementation(() => {});

            convertOptionsApiOverrideToCompositionApi('originalComponent', {
                methods: { foo() {} },
            });

            expect(consoleWarn).toHaveBeenCalledWith(
                expect.stringContaining(
                    'https://developer.shopware.com/docs/resources/references/core-reference/administration-reference/composition-api',
                ),
            );

            consoleWarn.mockRestore();
        });
    });

    describe('Full integration:', () => {
        it('should allow Options API method override on a Composition API component', async () => {
            _compositionApiComponents.add('originalComponent');

            const originalComponent = defineComponent({
                template: `
                    <div class="count">Count: {{ count }}</div>
                    <button @click="increment">Increment</button>
                `,
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        const count = ref(0);
                        const increment = () => {
                            count.value += 1;
                        };

                        return {
                            public: { count, increment },
                        };
                    }),
            });

            const wrapper = mount(originalComponent);
            expect(wrapper.find('.count').text()).toBe('Count: 0');

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                methods: {
                    increment() {
                        this.$super('increment');
                        this.$super('increment');
                    },
                },
            });

            _overridesMap.originalComponent.push(overrideFn);

            await flushPromises();

            await wrapper.find('button').trigger('click');
            // Two $super calls = +2
            expect(wrapper.find('.count').text()).toBe('Count: 2');
        });

        it('should allow Options API computed override on a Composition API component', async () => {
            _compositionApiComponents.add('originalComponent');

            const originalComponent = defineComponent({
                template: `
                    <div class="count">Count: {{ count }}</div>
                    <div class="label">Label: {{ label }}</div>
                `,
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        const count = ref(5);
                        const label = computed(() => `Value is ${count.value}`);

                        return {
                            public: { count, label },
                        };
                    }),
            });

            const wrapper = mount(originalComponent);
            expect(wrapper.find('.label').text()).toBe('Label: Value is 5');

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                computed: {
                    label() {
                        return `Custom: ${this.count}`;
                    },
                },
            });

            _overridesMap.originalComponent.push(overrideFn);

            await flushPromises();

            expect(wrapper.find('.label').text()).toBe('Label: Custom: 5');
        });

        it('should allow Options API data override on a Composition API component', async () => {
            _compositionApiComponents.add('originalComponent');

            const originalComponent = defineComponent({
                template: `
                    <div class="count">Count: {{ count }}</div>
                    <div class="extra">Extra: {{ extraInfo }}</div>
                `,
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        const count = ref(5);
                        const extraInfo = ref('none');

                        return {
                            public: { count, extraInfo },
                        };
                    }),
            });

            const wrapper = mount(originalComponent);
            expect(wrapper.find('.extra').text()).toBe('Extra: none');

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                data() {
                    return { extraInfo: 'overridden data' };
                },
            });

            _overridesMap.originalComponent.push(overrideFn);

            await flushPromises();

            expect(wrapper.find('.extra').text()).toBe('Extra: overridden data');
        });

        it('should allow combined methods + computed + data override', async () => {
            _compositionApiComponents.add('originalComponent');

            const originalComponent = defineComponent({
                template: `
                    <div class="count">Count: {{ count }}</div>
                    <div class="doubled">Doubled: {{ doubled }}</div>
                    <div class="extra">Extra: {{ extra }}</div>
                    <button @click="increment">Increment</button>
                `,
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        const count = ref(1);
                        const doubled = computed(() => count.value * 2);
                        const extra = ref('original');
                        const increment = () => {
                            count.value += 1;
                        };

                        return {
                            public: { count, doubled, extra, increment },
                        };
                    }),
            });

            const wrapper = mount(originalComponent);

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                data() {
                    return { extra: 'from-override' };
                },
                computed: {
                    doubled() {
                        return this.count * 10;
                    },
                },
                methods: {
                    increment() {
                        this.$super('increment');
                        this.$super('increment');
                    },
                },
            });

            _overridesMap.originalComponent.push(overrideFn);

            await flushPromises();

            expect(wrapper.find('.extra').text()).toBe('Extra: from-override');
            expect(wrapper.find('.doubled').text()).toBe('Doubled: 10');

            await wrapper.find('button').trigger('click');
            expect(wrapper.find('.count').text()).toBe('Count: 3');
            expect(wrapper.find('.doubled').text()).toBe('Doubled: 30');
        });
    });

    describe('Multi-level override chains:', () => {
        it('should support core -> Plugin A -> Plugin B override chain', async () => {
            _compositionApiComponents.add('originalComponent');

            const originalComponent = defineComponent({
                template: `
                    <div class="count">Count: {{ count }}</div>
                    <button @click="increment">Increment</button>
                `,
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        const count = ref(0);
                        const increment = () => {
                            count.value += 1;
                        };

                        return {
                            public: { count, increment },
                        };
                    }),
            });

            const wrapper = mount(originalComponent);
            expect(wrapper.find('.count').text()).toBe('Count: 0');

            // Plugin A override (Options API)
            const pluginAOverride = convertWithSilencedWarning('originalComponent', {
                methods: {
                    increment() {
                        this.$super('increment');
                        this.count += 10;
                    },
                },
            });

            _overridesMap.originalComponent.push(pluginAOverride);

            await flushPromises();

            // Plugin B override (Options API) - builds on Plugin A
            const pluginBOverride = convertWithSilencedWarning('originalComponent', {
                methods: {
                    increment() {
                        this.$super('increment');
                        this.count += 100;
                    },
                },
            });

            _overridesMap.originalComponent.push(pluginBOverride);

            await flushPromises();

            await wrapper.find('button').trigger('click');
            // Core: +1, Plugin A: +10, Plugin B: +100 = 111
            expect(wrapper.find('.count').text()).toBe('Count: 111');
        });

        it('should support multi-level chains with data overrides', async () => {
            _compositionApiComponents.add('originalComponent');

            const originalComponent = defineComponent({
                template: '<div class="msg">{{ message }}</div>',
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        const message = ref('core');

                        return {
                            public: { message },
                        };
                    }),
            });

            const wrapper = mount(originalComponent);
            expect(wrapper.find('.msg').text()).toBe('core');

            // Plugin A
            const pluginA = convertWithSilencedWarning('originalComponent', {
                data() {
                    return { message: 'plugin-a' };
                },
            });
            _overridesMap.originalComponent.push(pluginA);

            await flushPromises();

            expect(wrapper.find('.msg').text()).toBe('plugin-a');

            // Plugin B
            const pluginB = convertWithSilencedWarning('originalComponent', {
                data() {
                    return { message: 'plugin-b' };
                },
            });
            _overridesMap.originalComponent.push(pluginB);

            await flushPromises();

            expect(wrapper.find('.msg').text()).toBe('plugin-b');
        });

        it('should support multi-level chains with computed overrides', async () => {
            _compositionApiComponents.add('originalComponent');

            const originalComponent = defineComponent({
                template: `
                    <div class="count">Count: {{ count }}</div>
                    <div class="display">Display: {{ display }}</div>
                `,
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        const count = ref(5);
                        const display = computed(() => `Core: ${count.value}`);

                        return {
                            public: { count, display },
                        };
                    }),
            });

            const wrapper = mount(originalComponent);
            expect(wrapper.find('.display').text()).toBe('Display: Core: 5');

            // Plugin A: Override computed
            const pluginA = convertWithSilencedWarning('originalComponent', {
                computed: {
                    display() {
                        return `Plugin A: ${this.count}`;
                    },
                },
            });
            _overridesMap.originalComponent.push(pluginA);

            await flushPromises();

            expect(wrapper.find('.display').text()).toBe('Display: Plugin A: 5');

            // Plugin B: Override computed again
            const pluginB = convertWithSilencedWarning('originalComponent', {
                computed: {
                    display() {
                        return `Plugin B: ${this.count * 2}`;
                    },
                },
            });
            _overridesMap.originalComponent.push(pluginB);

            await flushPromises();

            expect(wrapper.find('.display').text()).toBe('Display: Plugin B: 10');
        });
    });

    describe('Edge cases:', () => {
        it('should handle override with only data and no existing methods', async () => {
            _compositionApiComponents.add('originalComponent');

            const originalComponent = defineComponent({
                template: '<div class="name">Name: {{ name }}</div>',
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        const name = ref('original');

                        return {
                            public: { name },
                        };
                    }),
            });

            const wrapper = mount(originalComponent);
            expect(wrapper.find('.name').text()).toBe('Name: original');

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                data() {
                    return { name: 'overridden' };
                },
            });

            _overridesMap.originalComponent.push(overrideFn);

            await flushPromises();

            expect(wrapper.find('.name').text()).toBe('Name: overridden');
        });

        it('should handle empty data function', () => {
            _compositionApiComponents.add('originalComponent');

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                data() {
                    return {};
                },
            });

            const result = overrideFn({}, {});

            expect(Object.keys(result)).toHaveLength(0);
        });

        it('should handle null/undefined data gracefully', () => {
            _compositionApiComponents.add('originalComponent');

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                data() {
                    return null as any;
                },
            });

            const result = overrideFn({}, {});
            expect(result).toBeDefined();
        });

        it('should handle override with only computed, no methods or data', async () => {
            _compositionApiComponents.add('originalComponent');

            const originalComponent = defineComponent({
                template: '<div class="display">{{ display }}</div>',
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        const count = ref(5);
                        const display = computed(() => `${count.value}`);

                        return {
                            public: { count, display },
                        };
                    }),
            });

            const wrapper = mount(originalComponent);
            expect(wrapper.find('.display').text()).toBe('5');

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                computed: {
                    display() {
                        return `Modified: ${this.count}`;
                    },
                },
            });

            _overridesMap.originalComponent.push(overrideFn);

            await flushPromises();

            expect(wrapper.find('.display').text()).toBe('Modified: 5');
        });

        it('should pass through inject config', () => {
            _compositionApiComponents.add('originalComponent');

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                inject: [
                    'repositoryFactory',
                    'acl',
                ],
                methods: { foo() {} },
            });

            const result = overrideFn({}, {});

            expect(result._inject).toEqual([
                'repositoryFactory',
                'acl',
            ]);
        });

        it('should handle config with no Options API patterns gracefully', () => {
            _compositionApiComponents.add('originalComponent');

            const overrideFn = convertWithSilencedWarning('originalComponent', {});

            const result = overrideFn({}, {});

            expect(result).toBeDefined();
            expect(typeof result).toBe('object');
        });

        it('should handle methods that return values', () => {
            _compositionApiComponents.add('originalComponent');

            const previousState = {
                count: ref(10),
            };

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                methods: {
                    getDoubledCount() {
                        return this.count * 2;
                    },
                },
            });

            const result = overrideFn(previousState, {});

            expect(result.getDoubledCount()).toBe(20);
        });

        it('should handle methods with arguments', () => {
            _compositionApiComponents.add('originalComponent');

            const previousState = {
                count: ref(0),
            };

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                methods: {
                    addToCount(amount: number) {
                        this.count += amount;
                    },
                },
            });

            const result = overrideFn(previousState, {});
            result.addToCount(42);

            expect(previousState.count.value).toBe(42);
        });
    });
});
