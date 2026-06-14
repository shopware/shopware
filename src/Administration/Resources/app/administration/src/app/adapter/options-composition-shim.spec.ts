/* eslint-disable sw-test-rules/test-file-max-lines-warning, sw-test-rules/test-file-max-lines-error */

/**
 * @sw-package framework
 */

/* eslint-disable @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-unsafe-call, @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-return, @typescript-eslint/no-unsafe-argument, @typescript-eslint/no-unused-vars */

import { createExtendableSetup, _overridesMap } from 'src/app/adapter/composition-extension-system';
import { shouldActivateShim, convertOptionsApiOverrideToCompositionApi } from 'src/app/adapter/options-composition-shim';
import type { OverrideFn } from 'src/app/adapter/options-composition-shim';
import { mount } from '@vue/test-utils';
import { ref, computed, defineComponent, nextTick, reactive, provide } from 'vue';

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

        jest.clearAllMocks();
    });

    describe('shouldActivateShim():', () => {
        it('should return true when override has methods', () => {
            const result = shouldActivateShim({
                methods: { save() {} },
            });

            expect(result).toBe(true);
        });

        it('should return true when override has computed', () => {
            const result = shouldActivateShim({
                computed: {
                    fullName() {
                        return '';
                    },
                },
            });

            expect(result).toBe(true);
        });

        it('should return true when override has data', () => {
            const result = shouldActivateShim({
                data() {
                    return { count: 0 };
                },
            });

            expect(result).toBe(true);
        });

        it('should return true when override has watch', () => {
            const result = shouldActivateShim({
                watch: { count() {} },
            });

            expect(result).toBe(true);
        });

        it('should return true when override has inject', () => {
            const result = shouldActivateShim({
                inject: ['repositoryFactory'],
            });

            expect(result).toBe(true);
        });

        it('should return true when override has mixins', () => {
            const result = shouldActivateShim({
                mixins: [{ methods: { foo() {} } }],
            });

            expect(result).toBe(true);
        });

        it('should return true when override has lifecycle hooks', () => {
            const result = shouldActivateShim({
                mounted() {},
            });

            expect(result).toBe(true);
        });

        it('should return true when mixin has lifecycle hooks', () => {
            const result = shouldActivateShim({
                mixins: [{ created() {} }],
            });

            expect(result).toBe(true);
        });

        it('should return false when override has no Options API patterns', () => {
            const result = shouldActivateShim({
                name: 'sw-example',
            });

            expect(result).toBe(false);
        });

        it('should return false for empty config', () => {
            const result = shouldActivateShim({});

            expect(result).toBe(false);
        });

        it('should return false for a normal template/setup component', () => {
            const result = shouldActivateShim({
                template: '<div>{{ count }}</div>',
                setup() {
                    return { count: ref(0) };
                },
            });

            expect(result).toBe(false);
        });

        it('should return false for an empty mixins array', () => {
            const result = shouldActivateShim({
                mixins: [],
            });

            expect(result).toBe(false);
        });

        it('should return true and emit unsupported warning when override only has extends', () => {
            const consoleWarn = jest.spyOn(console, 'warn').mockImplementation(() => {});

            const baseComponent = { methods: { foo() {} } };
            const result = shouldActivateShim({ extends: baseComponent } as any);

            expect(result).toBe(true);

            // Activating the shim path triggers checkUnsupportedFeatures, which warns about extends
            convertOptionsApiOverrideToCompositionApi('originalComponent', { extends: baseComponent } as any);

            const extendsWarnings = consoleWarn.mock.calls.filter(
                (call) => typeof call[0] === 'string' && call[0].includes('"extends" is not supported'),
            );
            expect(extendsWarnings).toHaveLength(1);

            consoleWarn.mockRestore();
        });
    });

    describe('convertData():', () => {
        it('should convert data() overriding an existing ref value', async () => {
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
            const overrideFn = convertWithSilencedWarning('originalComponent', {
                data() {
                    return { count: 42, name: 'test' };
                },
            });

            const result = overrideFn({}, {}) as Record<string, any>;

            expect(result.count.value).toBe(42);
            expect(result.name.value).toBe('test');
        });
    });

    describe('convertMethods():', () => {
        it('should convert methods and bind this to proxy', async () => {
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

            const result = overrideFn(previousState, {}) as Record<string, any>;

            expect(() => {
                result.doSomething();
            }).toThrow('$super: "nonExistentMethod" not found in previous state. It must be a method (function) or a ref.');
        });
    });

    describe('convertComputed():', () => {
        it('should convert getter-only computed properties', async () => {
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
            const originalComponent = defineComponent({
                template: `
                    <div class="count">Count: {{ count }}</div>
                    <div class="doubled">Doubled: {{ doubled }}</div>
                    <button @click="doubled = 8">Set doubled</button>
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

            // setter: doubled = 8 → count = 8 / 4 = 2, getter: 2 * 4 = 8
            await wrapper.find('button').trigger('click');
            await flushPromises();
            expect(wrapper.find('.count').text()).toBe('Count: 2');
            expect(wrapper.find('.doubled').text()).toBe('Doubled: 8');
        });

        it('should allow computed to access previousState values via this', async () => {
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
            const watchCallback = jest.fn();

            const originalComponent = defineComponent({
                template: '<div class="count">Count: {{ count }}</div>',
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        const count = ref(1);

                        function increment() {
                            count.value += 1;
                        }

                        return {
                            public: { count, increment },
                        };
                    }),
            });

            const wrapper = mount(originalComponent);

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                watch: {
                    count(newVal: number, oldVal: number) {
                        watchCallback(newVal, oldVal);
                    },
                },
            });

            _overridesMap.originalComponent.push(overrideFn);

            await flushPromises();

            wrapper.vm.increment();

            await flushPromises();
            await nextTick();

            expect(watchCallback).toHaveBeenCalledWith(2, 1);
        });

        it('should convert object watchers with immediate option', async () => {
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
            const methodCallback = jest.fn();

            const originalComponent = defineComponent({
                template: '<div class="count">Count: {{ count }}</div>',
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        const count = ref(1);

                        function increment() {
                            count.value += 1;
                        }

                        return {
                            public: { count, increment },
                        };
                    }),
            });

            const wrapper = mount(originalComponent);

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

            wrapper.vm.increment();

            await flushPromises();
            await nextTick();

            expect(methodCallback).toHaveBeenCalledWith(2, 1);
        });

        it('should log an error when the string method name watcher references a non-existent method', async () => {
            const consoleError = jest.spyOn(console, 'error').mockImplementation(() => {});
            const consoleWarn = jest.spyOn(console, 'warn').mockImplementation(() => {});

            const originalComponent = defineComponent({
                template: '<div class="count">Count: {{ count }}</div>',
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        const count = ref(1);

                        function increment() {
                            count.value += 1;
                        }

                        return {
                            public: { count, increment },
                        };
                    }),
            });

            const wrapper = mount(originalComponent);

            const overrideFn = convertOptionsApiOverrideToCompositionApi('originalComponent', {
                watch: {
                    count: 'nonExistentMethod',
                },
            }) as OverrideFn;

            _overridesMap.originalComponent.push(overrideFn);

            await flushPromises();

            wrapper.vm.increment();

            await flushPromises();
            await nextTick();

            expect(consoleError).toHaveBeenCalledWith(
                expect.stringContaining(
                    '[Options API Shim] Watch handler "nonExistentMethod" is not a function or does not exist on the component.',
                ),
            );

            consoleError.mockRestore();
            consoleWarn.mockRestore();
        });
    });

    describe('createThisProxy():', () => {
        it('should resolve this.propertyName to previousState ref values', () => {
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

            const result = overrideFn(previousState, {}) as Record<string, any>;

            expect(result.getCount()).toBe(42);
            expect(result.getName()).toBe('test');
        });

        it('should allow setting ref values via this.propertyName', () => {
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

            const result = overrideFn(previousState, {}) as Record<string, any>;
            result.setCount();

            expect(previousState.count.value).toBe(100);
        });

        it('should resolve props via this', () => {
            const previousState = {};
            const props = { title: 'Hello' };

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                methods: {
                    getTitle() {
                        return this.title;
                    },
                },
            });

            const result = overrideFn(previousState, props) as Record<string, any>;

            expect(result.getTitle()).toBe('Hello');
        });

        it('should prioritize local state over previousState', () => {
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

            const result = overrideFn(previousState, {}) as Record<string, any>;

            expect(result.getCount()).toBe(999);
        });

        it('should warn about accessing undefined properties', () => {
            const consoleWarn = jest.spyOn(console, 'warn').mockImplementation(() => {});

            const previousState = {};

            const overrideFn = convertOptionsApiOverrideToCompositionApi('originalComponent', {
                methods: {
                    accessUndefined() {
                        return this.nonExistentProp;
                    },
                },
            });

            const result = overrideFn(previousState, {}) as Record<string, any>;
            const value = result.accessUndefined();

            expect(value).toBeUndefined();
            expect(consoleWarn).toHaveBeenCalledWith(expect.stringContaining('Property "nonExistentProp" not found'));

            consoleWarn.mockRestore();
        });

        it('should not warn about Vue instance properties starting with $', () => {
            const consoleWarn = jest.spyOn(console, 'warn').mockImplementation(() => {});

            const previousState = {};

            const overrideFn = convertOptionsApiOverrideToCompositionApi('originalComponent', {
                methods: {
                    accessVueProperty() {
                        return this.$route;
                    },
                },
            });

            const result = overrideFn(previousState, {}) as Record<string, any>;
            result.accessVueProperty();

            // Filter out the deprecation warning to check only property warnings
            const propertyWarnings = consoleWarn.mock.calls.filter(
                (call) => typeof call[0] === 'string' && call[0].includes('not found in component state'),
            );
            expect(propertyWarnings).toHaveLength(0);

            consoleWarn.mockRestore();
        });

        it('should warn about unknown properties starting with _', () => {
            const consoleWarn = jest.spyOn(console, 'warn').mockImplementation(() => {});

            const previousState = {};

            const overrideFn = convertOptionsApiOverrideToCompositionApi('originalComponent', {
                methods: {
                    accessInternal() {
                        return this._internal;
                    },
                },
            });

            const result = overrideFn(previousState, {}) as Record<string, any>;
            result.accessInternal();

            // Filter out the deprecation warning to check only property warnings
            const propertyWarnings = consoleWarn.mock.calls.filter(
                (call) => typeof call[0] === 'string' && call[0].includes('not found in component state'),
            );
            expect(propertyWarnings).toHaveLength(1);
            expect(propertyWarnings[0][0]).toContain('"_internal"');

            consoleWarn.mockRestore();
        });

        it('should error when setting a property not found in any state', () => {
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

            const result = overrideFn(previousState, {}) as Record<string, any>;
            result.setUnknown();

            expect(consoleError).toHaveBeenCalledWith(expect.stringContaining('Cannot set property "unknownProp"'));

            consoleError.mockRestore();
            consoleWarn.mockRestore();
        });
    });

    describe('mergeMixins():', () => {
        it('should merge mixin methods into override config', async () => {
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

            const result = overrideFn({}, {}) as Record<string, any>;

            expect(result.mixinValue.value).toBe('from-mixin');
            expect(result.localValue.value).toBe('from-override');
        });

        it('should merge mixin lifecycle hooks and fire them', async () => {
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
                mixins: [
                    mixinA,
                    mixinB,
                ],
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

        it('should log warning for extends usage as it is unsupported', () => {
            const consoleWarn = jest.spyOn(console, 'warn').mockImplementation(() => {});

            const baseComponent = { methods: { base() {} } };
            convertOptionsApiOverrideToCompositionApi('originalComponent', {
                extends: baseComponent as any,
                methods: { foo() {} },
            });

            expect(consoleWarn).toHaveBeenCalledWith(expect.stringContaining('"extends" is not supported'));

            consoleWarn.mockRestore();
        });
    });

    describe('Deprecation warning:', () => {
        it('should log deprecation warning when shim is activated', () => {
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

        it('should allow Options API data override on a Composition API component', async () => {
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
            const overrideFn = convertWithSilencedWarning('originalComponent', {
                data() {
                    return {};
                },
            });

            const result = overrideFn({}, {});

            expect(Object.keys(result)).toHaveLength(0);
        });

        it('should handle null/undefined data gracefully', () => {
            const overrideFn = convertWithSilencedWarning('originalComponent', {
                data() {
                    return null as any;
                },
            });

            const result = overrideFn({}, {});
            expect(result).toBeDefined();
        });

        it('should handle override with only computed, no methods or data', async () => {
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

        it('should resolve inject internally without exposing keys in the override result', () => {
            // Suppress all warnings (deprecation + Vue "inject outside setup" since we call
            // the override function directly in this unit test, outside a component context).
            const consoleWarn = jest.spyOn(console, 'warn').mockImplementation(() => {});

            const overrideFn = convertOptionsApiOverrideToCompositionApi('originalComponent', {
                inject: [
                    'repositoryFactory',
                    'acl',
                ],
                methods: { foo() {} },
            });

            const result = overrideFn({}, {});

            // Inject values are resolved internally and made available via thisProxy.
            // They must NOT appear as keys in the override result so they don't
            // pollute the component's reactive state via applyOverrides.
            expect(result._inject).toBeUndefined();
            expect(Object.keys(result)).not.toContain('repositoryFactory');
            expect(Object.keys(result)).not.toContain('acl');

            consoleWarn.mockRestore();
        });

        it('should handle config with no Options API patterns gracefully', () => {
            const overrideFn = convertWithSilencedWarning('originalComponent', {});

            const result = overrideFn({}, {});

            expect(result).toBeDefined();
            expect(typeof result).toBe('object');
        });

        it('should handle methods that return values', () => {
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

            const result = overrideFn(previousState, {}) as Record<string, any>;

            expect(result.getDoubledCount()).toBe(20);
        });

        it('should handle methods with arguments', () => {
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

            const result = overrideFn(previousState, {}) as Record<string, any>;
            result.addToCount(42);

            expect(previousState.count.value).toBe(42);
        });
    });

    describe('inject resolution:', () => {
        it('should resolve array-form inject keys via this inside a lifecycle hook', async () => {
            const serviceInstance = { value: 'injected-value' };
            let capturedService: any = null;

            const originalComponent = defineComponent({
                template: '<div></div>',
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        return { public: {} };
                    }),
            });

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                inject: ['myService'],
                created() {
                    capturedService = this.myService;
                },
            });

            // Push BEFORE mount so inject() runs inside the component's setup() context
            // (triggered by the immediate watch in createExtendableSetup).
            _overridesMap.originalComponent = reactive([]);
            _overridesMap.originalComponent.push(overrideFn);

            mount(originalComponent, {
                global: { provide: { myService: serviceInstance } },
            });

            await flushPromises();

            expect(capturedService).toBe(serviceInstance);
        });

        it('should resolve object-form inject with from/default fallback', async () => {
            let capturedVal: any;

            const originalComponent = defineComponent({
                template: '<div></div>',
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        return { public: {} };
                    }),
            });

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                inject: { myVal: { from: 'nonExistentKey', default: 'fallback-value' } } as any,
                created() {
                    capturedVal = this.myVal;
                },
            });

            _overridesMap.originalComponent = reactive([]);
            _overridesMap.originalComponent.push(overrideFn);

            mount(originalComponent);

            await flushPromises();

            expect(capturedVal).toBe('fallback-value');
        });

        it('should not expose injected values as keys in the override result', async () => {
            let overrideResultKeys: string[] = [];

            const originalComponent = defineComponent({
                template: '<div></div>',
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        return { public: {} };
                    }),
            });

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                inject: ['someService'],
                created() {
                    // capture what was actually returned in the result (not injectedValues)
                },
            });

            // Wrap the override fn to inspect its result
            const wrappedFn = (previousState: any, props: any, context?: any) => {
                const result = overrideFn(previousState, props, context);
                overrideResultKeys = Object.keys(result);
                return result;
            };

            _overridesMap.originalComponent = reactive([]);
            _overridesMap.originalComponent.push(wrappedFn);

            mount(originalComponent, {
                global: { provide: { someService: {} } },
            });

            await flushPromises();

            expect(overrideResultKeys).not.toContain('someService');
            expect(overrideResultKeys).not.toContain('_inject');
        });
    });

    describe('flattenMixins() — recursive mixin resolution:', () => {
        it('should resolve lifecycle hooks from deeply nested mixins', async () => {
            const callOrder: string[] = [];

            const deepMixin = {
                created() {
                    callOrder.push('deep-mixin');
                },
            };

            const shallowMixin = {
                mixins: [deepMixin],
                created() {
                    callOrder.push('shallow-mixin');
                },
            };

            const originalComponent = defineComponent({
                template: '<div></div>',
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        return { public: {} };
                    }),
            });

            mount(originalComponent);

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                mixins: [shallowMixin],
                created() {
                    callOrder.push('component');
                },
            });

            _overridesMap.originalComponent.push(overrideFn);

            await flushPromises();

            // deep ancestor fires first, then shallow mixin, then component
            expect(callOrder).toEqual([
                'deep-mixin',
                'shallow-mixin',
                'component',
            ]);
        });

        it('should make methods from deeply nested mixins accessible via this', async () => {
            let capturedResult: string | null = null;

            const deepMixin = {
                methods: {
                    deepMethod() {
                        return 'from-deep-mixin';
                    },
                },
            };

            const shallowMixin = {
                mixins: [deepMixin],
            };

            const originalComponent = defineComponent({
                template: '<div></div>',
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        return { public: {} };
                    }),
            });

            mount(originalComponent);

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                mixins: [shallowMixin],
                created() {
                    capturedResult = (this as any).deepMethod();
                },
            });

            _overridesMap.originalComponent.push(overrideFn);

            await flushPromises();

            expect(capturedResult).toBe('from-deep-mixin');
        });
    });

    describe('setupWatchers() — dot-notation paths:', () => {
        it('should warn and skip dot-notation watch keys', async () => {
            // Use a single spy that covers all console.warn calls to avoid nested-spy issues.
            const consoleWarn = jest.spyOn(console, 'warn').mockImplementation(() => {});
            const watchCallback = jest.fn();

            const originalComponent = defineComponent({
                template: '<div></div>',
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        return { public: {} };
                    }),
            });

            mount(originalComponent);

            // Call convertOptionsApiOverrideToCompositionApi directly so the deprecation
            // warning is also captured by our single spy (filtered out below).
            const overrideFn = convertOptionsApiOverrideToCompositionApi('originalComponent', {
                watch: {
                    'user.name'(newVal: any) {
                        watchCallback(newVal);
                    },
                },
            });

            _overridesMap.originalComponent.push(overrideFn);

            await flushPromises();

            const dotNotationWarnings = consoleWarn.mock.calls.filter(
                (call) => typeof call[0] === 'string' && call[0].includes('Dot-notation watch path'),
            );

            expect(dotNotationWarnings).toHaveLength(1);
            expect(dotNotationWarnings[0][0]).toContain('"user.name"');
            expect(watchCallback).not.toHaveBeenCalled();

            consoleWarn.mockRestore();
        });

        it('should still process non-dot-notation watch keys alongside dot-notation ones', async () => {
            const consoleWarn = jest.spyOn(console, 'warn').mockImplementation(() => {});
            const flatCallback = jest.fn();

            const originalComponent = defineComponent({
                template: '<div class="count">{{ count }}</div>',
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        const count = ref(0);
                        return { public: { count } };
                    }),
            });

            const wrapper = mount(originalComponent);

            const overrideFn = convertOptionsApiOverrideToCompositionApi('originalComponent', {
                watch: {
                    'nested.prop'(newVal: any) {},
                    count(newVal: number) {
                        flatCallback(newVal);
                    },
                },
            });

            _overridesMap.originalComponent.push(overrideFn);

            await flushPromises();

            // Trigger a count change to fire the valid watcher
            _overridesMap.originalComponent.push((previousState: any) => {
                previousState.count.value = 99;
                return {};
            });

            await flushPromises();
            await nextTick();

            expect(flatCallback).toHaveBeenCalledWith(99);

            const dotNotationWarnings = consoleWarn.mock.calls.filter(
                (call) => typeof call[0] === 'string' && call[0].includes('Dot-notation watch path'),
            );
            expect(dotNotationWarnings).toHaveLength(1);

            consoleWarn.mockRestore();
            wrapper.unmount();
        });
    });

    describe('Vue instance property forwarding:', () => {
        it('should forward this.$emit() to the component instance', async () => {
            const originalComponent = defineComponent({
                template: '<button class="btn" @click="doEmit">Emit</button>',
                emits: ['custom-event'],
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        const doEmit = () => {};
                        return { public: { doEmit } };
                    }),
            });

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                methods: {
                    doEmit() {
                        this.$emit('custom-event', 'payload');
                    },
                },
            });

            // Push BEFORE mount so the proxy captures the component instance during setup
            _overridesMap.originalComponent = reactive([]);
            _overridesMap.originalComponent.push(overrideFn);

            const wrapper = mount(originalComponent);

            await flushPromises();

            await wrapper.find('.btn').trigger('click');

            expect(wrapper.emitted('custom-event')).toBeTruthy();
            expect(wrapper.emitted('custom-event')![0]).toEqual(['payload']);
        });

        it('should forward this.$nextTick() to the component instance', async () => {
            let nextTickResolved = false;

            const originalComponent = defineComponent({
                template: '<div class="count">{{ count }}</div>',
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        const count = ref(0);
                        return { public: { count } };
                    }),
            });

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                async created() {
                    if (this.$nextTick) {
                        await this.$nextTick(() => {
                            nextTickResolved = true;
                        });
                    }
                },
            });

            // Push BEFORE mount so the proxy captures the component instance during setup
            _overridesMap.originalComponent = reactive([]);
            _overridesMap.originalComponent.push(overrideFn);

            mount(originalComponent);

            await flushPromises();
            await nextTick();

            expect(nextTickResolved).toBe(true);
        });

        it('should forward this.$refs to the component instance', async () => {
            let capturedRef: any = null;

            const originalComponent = defineComponent({
                template: '<div ref="myDiv" class="target">hello</div>',
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        return { public: {} };
                    }),
            });

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                mounted() {
                    capturedRef = this.$refs;
                },
            });

            _overridesMap.originalComponent = reactive([]);
            _overridesMap.originalComponent.push(overrideFn);

            mount(originalComponent);

            await flushPromises();
            await nextTick();

            expect(capturedRef).toBeDefined();
            expect(capturedRef).not.toBeNull();
        });
    });

    describe('Deep nested reactive data:', () => {
        it('should maintain reactivity for deeply nested object data', async () => {
            const originalComponent = defineComponent({
                template: '<div class="city">{{ address.city }}</div>',
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        const address = ref({ city: 'initial' });
                        return { public: { address } };
                    }),
            });

            const wrapper = mount(originalComponent);

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                data() {
                    return { address: { city: 'Berlin' } };
                },
            });

            _overridesMap.originalComponent.push(overrideFn);

            await flushPromises();

            expect(wrapper.find('.city').text()).toBe('Berlin');
        });

        it('should handle nested object data with deep reactivity', () => {
            const overrideFn = convertWithSilencedWarning('originalComponent', {
                data() {
                    return {
                        user: { address: { city: 'Berlin' } },
                    };
                },
            });

            const result = overrideFn({}, {}) as Record<string, any>;

            expect(result.user.value.address.city).toBe('Berlin');

            result.user.value.address.city = 'Hamburg';
            expect(result.user.value.address.city).toBe('Hamburg');
        });
    });

    describe('Mixin inject merging:', () => {
        it('should resolve inject from mixin via this in a method', async () => {
            const serviceInstance = { load: () => 'loaded' };
            let capturedService: any = null;

            const myMixin = {
                inject: ['repositoryFactory'] as any,
            };

            const originalComponent = defineComponent({
                template: '<div></div>',
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        return { public: {} };
                    }),
            });

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                mixins: [myMixin],
                created() {
                    capturedService = this.repositoryFactory;
                },
            });

            _overridesMap.originalComponent = reactive([]);
            _overridesMap.originalComponent.push(overrideFn);

            mount(originalComponent, {
                global: { provide: { repositoryFactory: serviceInstance } },
            });

            await flushPromises();

            expect(capturedService).toBe(serviceInstance);
        });

        it('should resolve inject from deeply nested mixin', async () => {
            let capturedAcl: any = null;

            const deepMixin = {
                inject: ['acl'] as any,
            };

            const shallowMixin = {
                mixins: [deepMixin],
            };

            const originalComponent = defineComponent({
                template: '<div></div>',
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        return { public: {} };
                    }),
            });

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                mixins: [shallowMixin],
                created() {
                    capturedAcl = this.acl;
                },
            });

            _overridesMap.originalComponent = reactive([]);
            _overridesMap.originalComponent.push(overrideFn);

            mount(originalComponent, {
                global: { provide: { acl: { can: () => true } } },
            });

            await flushPromises();

            expect(capturedAcl).toBeDefined();
            expect(capturedAcl.can()).toBe(true);
        });

        it('should let component inject win over mixin inject on conflict', async () => {
            let capturedVal: any = null;

            const myMixin = {
                inject: { myService: { from: 'myService', default: 'mixin-default' } } as any,
            };

            const originalComponent = defineComponent({
                template: '<div></div>',
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        return { public: {} };
                    }),
            });

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                mixins: [myMixin],
                inject: { myService: { from: 'myService', default: 'component-default' } } as any,
                created() {
                    capturedVal = this.myService;
                },
            });

            _overridesMap.originalComponent = reactive([]);
            _overridesMap.originalComponent.push(overrideFn);

            mount(originalComponent);

            await flushPromises();

            expect(capturedVal).toBe('component-default');
        });
    });

    describe('Explicit undefined property handling:', () => {
        it('should return undefined from local state when data explicitly sets a value to undefined', () => {
            const previousState = {
                selectedId: ref('previous-value'),
            };

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                data() {
                    return { selectedId: undefined };
                },
                methods: {
                    getSelectedId() {
                        return this.selectedId;
                    },
                },
            });

            const result = overrideFn(previousState, {}) as Record<string, any>;

            expect(result.getSelectedId()).toBeUndefined();
        });
    });

    describe('Watch flush option:', () => {
        it('should forward flush option to Vue watch', async () => {
            const watchCallback = jest.fn();
            let domTextDuringCallback: string | null = null;

            const originalComponent = defineComponent({
                template: '<div class="count">{{ count }}</div>',
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        const count = ref(0);
                        return { public: { count } };
                    }),
            });

            const wrapper = mount(originalComponent);

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                watch: {
                    count: {
                        handler(newVal: number) {
                            // With flush: 'post', the DOM should already be updated when this callback fires.
                            // With flush: 'pre' (default), domTextDuringCallback would still be '0'.
                            domTextDuringCallback = wrapper.find('.count').text();
                            watchCallback(newVal);
                        },
                        flush: 'post',
                    },
                },
            });

            _overridesMap.originalComponent.push(overrideFn);

            await flushPromises();

            _overridesMap.originalComponent.push((previousState: any) => {
                previousState.count.value = 42;
                return {};
            });

            await flushPromises();
            await nextTick();

            expect(watchCallback).toHaveBeenCalledWith(42);
            // Verify flush: 'post' timing — the DOM must already show the updated value
            // when the callback fires (this assertion would fail with flush: 'pre').
            expect(domTextDuringCallback).toBe('42');
        });
    });

    describe('Watch handler arrays:', () => {
        it('should support array of handlers for a single watch key', async () => {
            const callback1 = jest.fn();
            const callback2 = jest.fn();

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
                watch: {
                    count: [
                        function (this: any, newVal: number) {
                            callback1(newVal);
                        },
                        {
                            handler(newVal: number) {
                                callback2(newVal);
                            },
                            immediate: true,
                        },
                    ],
                },
            });

            _overridesMap.originalComponent.push(overrideFn);

            await flushPromises();
            await nextTick();

            // The immediate handler should have fired already
            expect(callback2).toHaveBeenCalled();

            // Trigger a change
            _overridesMap.originalComponent.push((previousState: any) => {
                previousState.count.value = 99;
                return {};
            });

            await flushPromises();
            await nextTick();

            expect(callback1).toHaveBeenCalledWith(99);
            expect(callback2).toHaveBeenCalledWith(99);
        });
    });

    describe('Extended unsupported features:', () => {
        it('should warn when override uses components option', () => {
            const consoleWarn = jest.spyOn(console, 'warn').mockImplementation(() => {});

            convertOptionsApiOverrideToCompositionApi('originalComponent', {
                components: { SomeComponent: {} } as any,
                methods: { foo() {} },
            });

            const relevantWarnings = consoleWarn.mock.calls.filter(
                (call) => typeof call[0] === 'string' && call[0].includes('"components" is not supported'),
            );
            expect(relevantWarnings).toHaveLength(1);

            consoleWarn.mockRestore();
        });

        it('should warn when override uses provide option', () => {
            const consoleWarn = jest.spyOn(console, 'warn').mockImplementation(() => {});

            convertOptionsApiOverrideToCompositionApi('originalComponent', {
                provide: { someKey: 'someValue' } as any,
                methods: { foo() {} },
            });

            const relevantWarnings = consoleWarn.mock.calls.filter(
                (call) => typeof call[0] === 'string' && call[0].includes('"provide" is not supported'),
            );
            expect(relevantWarnings).toHaveLength(1);

            consoleWarn.mockRestore();
        });

        it('should warn when override uses template option', () => {
            const consoleWarn = jest.spyOn(console, 'warn').mockImplementation(() => {});

            convertOptionsApiOverrideToCompositionApi('originalComponent', {
                template: '<div>override</div>',
                methods: { foo() {} },
            });

            const relevantWarnings = consoleWarn.mock.calls.filter(
                (call) => typeof call[0] === 'string' && call[0].includes('"template" is not supported'),
            );
            expect(relevantWarnings).toHaveLength(1);

            consoleWarn.mockRestore();
        });

        it('should warn for multiple unsupported options at once', () => {
            const consoleWarn = jest.spyOn(console, 'warn').mockImplementation(() => {});

            convertOptionsApiOverrideToCompositionApi('originalComponent', {
                components: { Foo: {} } as any,
                directives: { focus: {} } as any,
                emits: ['foo'] as any,
                methods: { foo() {} },
            });

            const shimWarnings = consoleWarn.mock.calls.filter(
                (call) => typeof call[0] === 'string' && call[0].includes('is not supported by the compatibility shim'),
            );
            expect(shimWarnings.length).toBeGreaterThanOrEqual(3);

            consoleWarn.mockRestore();
        });
    });

    describe('Component unmount cleanup:', () => {
        it('should not fire watchers after component unmount', async () => {
            const watchCallback = jest.fn();

            const originalComponent = defineComponent({
                template: '<div class="count">{{ count }}</div>',
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        const count = ref(0);
                        return { public: { count } };
                    }),
            });

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                watch: {
                    count(newVal: number) {
                        watchCallback(newVal);
                    },
                },
            });

            _overridesMap.originalComponent = reactive([]);
            _overridesMap.originalComponent.push(overrideFn);

            const wrapper = mount(originalComponent);

            await flushPromises();

            wrapper.unmount();

            watchCallback.mockClear();

            await nextTick();

            expect(watchCallback).not.toHaveBeenCalled();
        });
    });

    describe('Error recovery:', () => {
        it('should propagate errors thrown in created without catching them, and still apply subsequent overrides', async () => {
            const capturedErrors: unknown[] = [];

            const originalComponent = defineComponent({
                template: '<div class="count">{{ count }}</div>',
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        const count = ref(0);
                        return { public: { count } };
                    }),
            });

            const wrapper = mount(originalComponent, {
                global: {
                    config: {
                        errorHandler: (err: unknown) => {
                            capturedErrors.push(err);
                        },
                    },
                },
            });

            // Override that throws in created - matching Vue's native behavior, the error
            // must propagate and the override must not be applied (count stays at 0).
            const failingOverride = convertWithSilencedWarning('originalComponent', {
                data() {
                    return { count: 50 };
                },
                created() {
                    throw new Error('Simulated error in created hook');
                },
            });

            _overridesMap.originalComponent.push(failingOverride);

            await flushPromises();

            expect(capturedErrors).toHaveLength(1);
            expect(capturedErrors[0]).toBeInstanceOf(Error);
            expect((capturedErrors[0] as Error).message).toBe('Simulated error in created hook');

            // Override data must not be applied because the override function threw before returning
            expect(wrapper.find('.count').text()).toBe('0');

            // Subsequent overrides must still apply — the failing one is marked as done to prevent retries
            const successOverride = convertWithSilencedWarning('originalComponent', {
                data() {
                    return { count: 100 };
                },
            });

            _overridesMap.originalComponent.push(successOverride);

            await flushPromises();

            expect(wrapper.find('.count').text()).toBe('100');
        });
    });

    describe('getCurrentInstance behavior:', () => {
        it('should forward Vue instance properties when override is applied during setup (getCurrentInstance returns instance)', async () => {
            // When the override is pre-registered before mount, applyOverrides runs inside the
            // setup() watcher with immediate:true → getCurrentInstance() returns the component
            // instance → $emit/$refs/$nextTick etc. are forwarded via the proxy.
            const originalComponent = defineComponent({
                template: '<button class="btn" @click="doEmit">Emit</button>',
                emits: ['my-event'],
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        const doEmit = () => {};
                        return { public: { doEmit } };
                    }),
            });

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                methods: {
                    doEmit() {
                        this.$emit('my-event', 'hello');
                    },
                },
            });

            // Push BEFORE mount so createThisProxy captures the real instance via getCurrentInstance()
            _overridesMap.originalComponent = reactive([]);
            _overridesMap.originalComponent.push(overrideFn);

            const wrapper = mount(originalComponent);
            await flushPromises();

            await wrapper.find('.btn').trigger('click');

            expect(wrapper.emitted('my-event')).toBeTruthy();
            expect(wrapper.emitted('my-event')![0]).toEqual(['hello']);
        });

        it('should return undefined for Vue instance properties when override is applied late (getCurrentInstance returns null)', async () => {
            // When the override is pushed AFTER mount, applyOverrides runs in an async watcher
            // callback outside of setup() → getCurrentInstance() returns null → the proxy cannot
            // access the component instance and returns undefined for $-prefixed properties.
            let capturedEmit: unknown = 'not-set';

            const originalComponent = defineComponent({
                template: '<div class="result">{{ result }}</div>',
                emits: ['my-event'],
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        const result = ref('initial');
                        return { public: { result } };
                    }),
            });

            mount(originalComponent);

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                created() {
                    // $emit should be undefined because getCurrentInstance() was null when the
                    // override was applied after mount (outside setup() context).
                    capturedEmit = this.$emit;
                },
                methods: { noop() {} },
            });

            // Push AFTER mount → getCurrentInstance() is null inside createThisProxy
            _overridesMap.originalComponent.push(overrideFn);
            await flushPromises();

            expect(capturedEmit).toBeUndefined();
        });

        it('should log a warning for future lifecycle hooks (e.g. beforeUnmount) when applied late (getCurrentInstance returns null)', async () => {
            // When the override is pushed after mount, getCurrentInstance() is null in
            // setupLifecycleHooks(), so future hooks that cannot be registered via onBeforeUnmount()
            // should emit a console.warn instead of silently being lost.
            const originalComponent = defineComponent({
                template: '<div></div>',
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        return { public: {} };
                    }),
            });

            mount(originalComponent);

            // convertWithSilencedWarning internally calls spy.mockRestore(), so our consoleWarn spy
            // must be set up AFTER the call to avoid it being restored before the hook warning fires.
            const overrideFn = convertWithSilencedWarning('originalComponent', {
                beforeUnmount() {},
                methods: { noop() {} },
            });

            const consoleWarn = jest.spyOn(console, 'warn').mockImplementation(() => {});

            // Push AFTER mount → getCurrentInstance() is null → beforeUnmount cannot be registered
            _overridesMap.originalComponent.push(overrideFn);
            await flushPromises();

            expect(consoleWarn).toHaveBeenCalledWith(
                expect.stringContaining('[Options API Shim] Lifecycle hook "beforeUnmount" could not be registered'),
            );

            consoleWarn.mockRestore();
        });

        it('should invoke already-passed lifecycle hooks immediately when applied late (getCurrentInstance returns null)', async () => {
            // When the override is pushed after mount, beforeCreate/created/beforeMount/mounted
            // have already fired. The shim handles this by invoking those hooks synchronously
            // even though getCurrentInstance() is null at that point.
            const createdCallback = jest.fn();
            const mountedCallback = jest.fn();

            const originalComponent = defineComponent({
                template: '<div></div>',
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        return { public: {} };
                    }),
            });

            mount(originalComponent);

            const overrideFn = convertWithSilencedWarning('originalComponent', {
                created() {
                    createdCallback();
                },
                mounted() {
                    mountedCallback();
                },
                methods: { noop() {} },
            });

            // Push AFTER mount → both hooks are "already passed" → both must fire immediately
            _overridesMap.originalComponent.push(overrideFn);
            await flushPromises();

            expect(createdCallback).toHaveBeenCalledTimes(1);
            expect(mountedCallback).toHaveBeenCalledTimes(1);
        });
    });

    describe('Concurrent override application:', () => {
        it('should apply two overrides pushed in the same tick', async () => {
            const originalComponent = defineComponent({
                template: `
                    <div class="count">{{ count }}</div>
                    <div class="label">{{ label }}</div>
                `,
                setup: (props, context) =>
                    createExtendableSetup({ props, context, name: 'originalComponent' }, () => {
                        const count = ref(0);
                        const label = computed(() => `Value: ${count.value}`);
                        return { public: { count, label } };
                    }),
            });

            const wrapper = mount(originalComponent);

            const overrideA = convertWithSilencedWarning('originalComponent', {
                data() {
                    return { count: 42 };
                },
            });

            const overrideB = convertWithSilencedWarning('originalComponent', {
                computed: {
                    label() {
                        return `Custom: ${this.count}`;
                    },
                },
            });

            // Push both in the same tick
            _overridesMap.originalComponent.push(overrideA);
            _overridesMap.originalComponent.push(overrideB);

            await flushPromises();

            expect(wrapper.find('.count').text()).toBe('42');
            expect(wrapper.find('.label').text()).toBe('Custom: 42');
        });
    });
});
