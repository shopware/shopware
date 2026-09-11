/**
 * @sw-package framework
 */

import { createExtendableSetup, _overridesMap } from 'src/app/adapter/composition-extension-system';
import { convertOptionsApiOverrideToCompositionApi } from 'src/app/adapter/options-composition-shim';

import { mount } from '@vue/test-utils';
import { ref, defineComponent, nextTick } from 'vue';

import { convertWithSilencedWarning } from './fixtures';

describe('Options composition shim', () => {
    beforeEach(() => {
        const entries = [...Object.keys(_overridesMap)];
        entries.forEach((key) => {
            delete _overridesMap[key];
        });

        jest.clearAllMocks();
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
            });

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
});
