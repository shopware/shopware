/**
 * @sw-package framework
 */

/* eslint-disable @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-call */

import { createExtendableSetup, _overridesMap } from 'src/app/adapter/composition-extension-system';

import { mount } from '@vue/test-utils';
import { ref, computed, defineComponent } from 'vue';

import { convertWithSilencedWarning } from './fixtures';

describe('Options composition shim', () => {
    beforeEach(() => {
        const entries = [...Object.keys(_overridesMap)];
        entries.forEach((key) => {
            delete _overridesMap[key];
        });

        jest.clearAllMocks();
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
});
