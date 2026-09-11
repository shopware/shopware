/**
 * @sw-package framework
 */

/* eslint-disable @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-call, @typescript-eslint/no-explicit-any */

import { createExtendableSetup, _overridesMap } from 'src/app/adapter/composition-extension-system';

import { mount } from '@vue/test-utils';
import { ref, defineComponent } from 'vue';

import { convertWithSilencedWarning } from './fixtures';

describe('Options composition shim', () => {
    beforeEach(() => {
        const entries = [...Object.keys(_overridesMap)];
        entries.forEach((key) => {
            delete _overridesMap[key];
        });

        jest.clearAllMocks();
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
});
