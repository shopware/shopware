/**
 * @sw-package framework
 */

/* eslint-disable @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-call */

import { ref } from 'vue';
import { mount } from '@vue/test-utils';
import { _overridesMap } from 'src/app/adapter/composition-extension-system';
import { defineExtendable, registerOptionsOverride } from './test-utils';

const name = 'sw-shim-lifecycle';

const createCounter = () =>
    defineExtendable({ name, template: '<div class="count">{{ count }}</div><button @click="increment" />' }, () => {
        const count = ref(1);
        const increment = () => {
            count.value += 1;
        };

        return { public: { count, increment } };
    });

describe('src/app/adapter/options-composition-shim: lifecycle hooks and mixins', () => {
    beforeEach(() => {
        _overridesMap.clear();
    });

    it('runs beforeCreate and created during setup and the other hooks at their time', async () => {
        const hooks: string[] = [];
        registerOptionsOverride(name, {
            beforeCreate() {
                hooks.push('beforeCreate');
            },
            created() {
                hooks.push(`created:${this.count as number}`);
            },
            beforeMount() {
                hooks.push('beforeMount');
            },
            mounted() {
                hooks.push('mounted');
            },
            beforeUpdate() {
                hooks.push('beforeUpdate');
            },
            updated() {
                hooks.push('updated');
            },
            beforeUnmount() {
                hooks.push('beforeUnmount');
            },
            unmounted() {
                hooks.push('unmounted');
            },
        });

        const wrapper = mount(createCounter());
        await wrapper.get('button').trigger('click');
        wrapper.unmount();

        expect(hooks).toEqual([
            'beforeCreate',
            'created:1',
            'beforeMount',
            'mounted',
            'beforeUpdate',
            'updated',
            'beforeUnmount',
            'unmounted',
        ]);
    });

    it('works together with data and watch of the same override', async () => {
        const watched = jest.fn();
        registerOptionsOverride(name, {
            data: () => ({ count: 10 }),
            watch: { count: watched },
            created() {
                this.count += 1;
            },
        });

        const wrapper = mount(createCounter());
        await flushPromises();

        expect(wrapper.get('.count').text()).toBe('11');
        expect(watched).toHaveBeenCalledWith(11, 10);
    });

    it('merges methods and data of mixins, the override winning on conflicts', async () => {
        registerOptionsOverride(name, {
            mixins: [
                {
                    data: () => ({ count: 50, fromMixin: 'mixin' }),
                    methods: {
                        increment(this: { count: number }) {
                            this.count += 1000;
                        },
                        helper() {
                            return 7;
                        },
                    },
                },
            ],
            data: () => ({ count: 20 }),
            methods: {
                increment() {
                    this.count += this.helper() as number;
                },
            },
        });

        const wrapper = mount(createCounter());
        await wrapper.get('button').trigger('click');

        expect(wrapper.get('.count').text()).toBe('27');
    });

    it('runs the hooks of nested mixins first, then those of the override', () => {
        const hooks: string[] = [];
        const deepMixin = {
            created() {
                hooks.push('deep');
            },
            methods: {
                deepMethod() {
                    return 'deep method';
                },
            },
        };
        registerOptionsOverride(name, {
            mixins: [
                {
                    mixins: [deepMixin],
                    created() {
                        hooks.push('mixin');
                    },
                },
                {
                    created() {
                        hooks.push('second mixin');
                    },
                },
            ],
            created() {
                hooks.push(`override, ${this.deepMethod() as string}`);
            },
        });

        mount(createCounter());

        expect(hooks).toEqual([
            'deep',
            'mixin',
            'second mixin',
            'override, deep method',
        ]);
    });
});
