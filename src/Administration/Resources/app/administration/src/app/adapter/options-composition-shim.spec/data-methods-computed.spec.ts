/**
 * @sw-package framework
 */

/* eslint-disable @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-call */

import { computed, isRef, ref } from 'vue';
import { mount } from '@vue/test-utils';
import { _overridesMap } from 'src/app/adapter/composition-extension-system';
import { convert, defineExtendable, registerOptionsOverride } from './test-utils';

const name = 'sw-shim-data-methods-computed';

const createCounter = () =>
    defineExtendable(
        {
            name,
            template:
                '<div class="count">{{ count }}</div><div class="label">{{ label }}</div><input v-model="text"><button @click="increment" />',
        },
        () => {
            const count = ref(1);
            const text = ref('');
            const label = computed(() => `Count ${count.value}`);
            const increment = () => {
                count.value += 1;
            };

            return { public: { count, text, label, increment } };
        },
    );

describe('src/app/adapter/options-composition-shim: data, methods and computed', () => {
    beforeEach(() => {
        _overridesMap.clear();
    });

    describe('data', () => {
        it('turns every data key into a ref', () => {
            const result = convert(name, { data: () => ({ count: 42, nested: { deep: 'value' } }) })({}, {});

            expect(isRef(result.count)).toBe(true);
            expect((result.count as { value: number }).value).toBe(42);
            expect((result.nested as { value: { deep: string } }).value.deep).toBe('value');
        });

        it('handles data that returns nothing', () => {
            expect(convert(name, { data: () => null })({}, {})).toEqual({});
            expect(convert(name, { data: () => ({}) })({}, {})).toEqual({});
        });

        it('keeps an explicitly undefined data key', () => {
            const result = convert(name, { data: () => ({ value: undefined }) })({}, {});

            expect(Object.hasOwn(result, 'value')).toBe(true);
            expect((result.value as { value: unknown }).value).toBeUndefined();
        });

        it('overrides a base ref and stays reactive', async () => {
            registerOptionsOverride(name, { data: () => ({ count: 10 }) });

            const wrapper = mount(createCounter());
            expect(wrapper.get('.count').text()).toBe('10');

            await wrapper.get('button').trigger('click');
            await wrapper.get('button').trigger('click');
            expect(wrapper.get('.count').text()).toBe('12');
            expect(wrapper.get('.label').text()).toBe('Count 12');
        });

        it('keeps v-model working on an overridden text ref', async () => {
            registerOptionsOverride(name, { data: () => ({ text: 'from override' }) });

            const wrapper = mount(createCounter());
            expect((wrapper.get('input').element as HTMLInputElement).value).toBe('from override');

            await wrapper.get('input').setValue('typed');
            expect((wrapper.vm as unknown as { text: string }).text).toBe('typed');
        });
    });

    describe('methods', () => {
        it('replaces a method and binds this to the component state', async () => {
            registerOptionsOverride(name, {
                methods: {
                    increment() {
                        this.count += 10;
                    },
                },
            });

            const wrapper = mount(createCounter());
            await wrapper.get('button').trigger('click');

            expect(wrapper.get('.count').text()).toBe('11');
        });

        it('calls the previous method through this.$super()', async () => {
            registerOptionsOverride(name, {
                methods: {
                    increment() {
                        this.$super('increment');
                        this.count += 5;
                    },
                },
            });

            const wrapper = mount(createCounter());
            await wrapper.get('button').trigger('click');

            expect(wrapper.get('.count').text()).toBe('7');
        });

        it('chains this.$super() through several Options API overrides', async () => {
            registerOptionsOverride(name, {
                methods: {
                    increment() {
                        this.$super('increment');
                        this.count *= 10;
                    },
                },
            });
            registerOptionsOverride(name, {
                methods: {
                    increment() {
                        this.$super('increment');
                        this.count += 1;
                    },
                },
            });

            const wrapper = mount(createCounter());
            await wrapper.get('button').trigger('click');

            expect(wrapper.get('.count').text()).toBe('21');
        });

        it('passes arguments and return values through', () => {
            const result = convert(name, {
                methods: {
                    add(a: number, b: number) {
                        return a + b;
                    },
                },
            })({}, {});

            expect((result.add as (a: number, b: number) => number)(2, 3)).toBe(5);
        });

        it('throws when this.$super() names neither a method nor a ref', () => {
            const result = convert(name, {
                methods: {
                    save() {
                        this.$super('missing');
                    },
                },
            })({ count: ref(1) }, {});

            expect(() => (result.save as () => void)()).toThrow(
                '$super: "missing" not found in previous state. It must be a method (function) or a ref.',
            );
        });
    });

    describe('computed', () => {
        it('replaces a computed and re-evaluates it when the state changes', async () => {
            registerOptionsOverride(name, {
                computed: {
                    label() {
                        return `Custom ${this.count as number}`;
                    },
                },
            });

            const wrapper = mount(createCounter());
            expect(wrapper.get('.label').text()).toBe('Custom 1');

            await wrapper.get('button').trigger('click');
            expect(wrapper.get('.label').text()).toBe('Custom 2');
        });

        it('reads the previous computed through this.$super()', () => {
            registerOptionsOverride(name, {
                computed: {
                    label() {
                        return `${this.$super('label') as string}!`;
                    },
                },
            });

            expect(mount(createCounter()).get('.label').text()).toBe('Count 1!');
        });

        it('supports a getter and a setter', async () => {
            registerOptionsOverride(name, {
                computed: {
                    doubled: {
                        get(this: { count: number }) {
                            return this.count * 2;
                        },
                        set(this: { count: number }, value: number) {
                            this.count = value / 2;
                        },
                    },
                },
            });

            const component = defineExtendable(
                { name, template: '<div>{{ doubled }}</div><button @click="doubled = 10" />' },
                () => ({ public: { count: ref(1), doubled: computed(() => 0) } }),
            );
            const wrapper = mount(component);
            expect(wrapper.text()).toBe('2');

            await wrapper.get('button').trigger('click');
            expect(wrapper.text()).toBe('10');
        });

        it('skips a computed with a setter but no getter', () => {
            const error = jest.spyOn(console, 'error').mockImplementation(() => {});

            const result = convert(name, { computed: { broken: { set() {} } } })({}, {});

            expect(result).toEqual({});
            expect(error).toHaveBeenCalledWith(
                expect.stringContaining('Computed property "broken" has a setter but no getter'),
            );
            error.mockRestore();
        });
    });

    it('applies data, computed and methods of one override together', async () => {
        registerOptionsOverride(name, {
            data: () => ({ count: 5 }),
            computed: {
                label() {
                    return `Total ${this.count as number}`;
                },
            },
            methods: {
                increment() {
                    this.count += 100;
                },
            },
        });

        const wrapper = mount(createCounter());
        await wrapper.get('button').trigger('click');

        expect(wrapper.get('.count').text()).toBe('105');
        expect(wrapper.get('.label').text()).toBe('Total 105');
    });
});
