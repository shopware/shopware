/**
 * @sw-package framework
 */

/* eslint-disable @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-explicit-any */

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
});
