/**
 * @sw-package framework
 */

/* eslint-disable @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-explicit-any */

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
});
