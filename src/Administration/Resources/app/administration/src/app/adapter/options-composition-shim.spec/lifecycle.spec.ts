/**
 * @sw-package framework
 */

/* eslint-disable @typescript-eslint/no-unsafe-assignment */

import { createExtendableSetup, _overridesMap } from 'src/app/adapter/composition-extension-system';

import { mount } from '@vue/test-utils';
import { ref, defineComponent, nextTick, reactive } from 'vue';

import { convertWithSilencedWarning } from './fixtures';

describe('Options composition shim', () => {
    beforeEach(() => {
        const entries = [...Object.keys(_overridesMap)];
        entries.forEach((key) => {
            delete _overridesMap[key];
        });

        jest.clearAllMocks();
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
});
