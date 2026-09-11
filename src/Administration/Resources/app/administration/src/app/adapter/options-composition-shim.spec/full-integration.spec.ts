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
});
