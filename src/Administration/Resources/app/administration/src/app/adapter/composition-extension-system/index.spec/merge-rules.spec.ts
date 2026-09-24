/**
 * @sw-package framework
 */

/* eslint-disable @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-call */

import { computed, reactive, ref } from 'vue';
import { mount } from '@vue/test-utils';
import { _overridesMap, overrideComponentSetup } from 'src/app/adapter/composition-extension-system';
import { defineExtendable } from './test-utils';

const name = 'sw-merge-rules';

describe('src/app/adapter/composition-extension-system: merging an override result', () => {
    beforeEach(() => {
        _overridesMap.clear();
    });

    describe('refs', () => {
        const createCounter = () =>
            defineExtendable(
                {
                    name,
                    template: '<div class="count">{{ count }}</div><button @click="increment" />',
                },
                () => {
                    const count = ref(1);
                    const increment = () => {
                        count.value += 1;
                    };

                    return { public: { count, increment } };
                },
            );

        it('syncs a plain ref with the base ref, so the base keeps working on the new value', async () => {
            overrideComponentSetup()(name, (previousState) => ({
                count: ref(previousState.count.value + 5),
            }));

            const wrapper = mount(createCounter());
            expect(wrapper.get('.count').text()).toBe('6');

            await wrapper.get('button').trigger('click');
            expect(wrapper.get('.count').text()).toBe('7');
        });

        it('keeps both refs in sync in both directions', async () => {
            const overrideCount = ref(10);
            overrideComponentSetup()(name, () => ({ count: overrideCount }));

            const wrapper = mount(createCounter());
            await wrapper.get('button').trigger('click');
            expect(overrideCount.value).toBe(11);

            overrideCount.value = 20;
            await flushPromises();
            expect(wrapper.get('.count').text()).toBe('20');
        });

        it('lets an override change the previous ref before it replaces it', () => {
            overrideComponentSetup()(name, (previousState) => {
                previousState.count.value *= 5;

                return { count: ref(previousState.count.value + 5) };
            });

            expect(mount(createCounter()).get('.count').text()).toBe('10');
        });

        it('writes a primitive into the base ref', async () => {
            overrideComponentSetup()(name, () => ({ count: 40 }));

            const wrapper = mount(createCounter());
            expect(wrapper.get('.count').text()).toBe('40');

            await wrapper.get('button').trigger('click');
            expect(wrapper.get('.count').text()).toBe('41');
        });
    });

    describe('computeds', () => {
        const createDoubled = () =>
            defineExtendable(
                {
                    name,
                    template: '<div class="doubled">{{ doubled }}</div><button @click="doubled = 10" />',
                },
                () => {
                    const count = ref(1);
                    const doubled = computed({
                        get: () => count.value * 2,
                        set: (value: number) => {
                            count.value = value / 2;
                        },
                    });

                    return { public: { count, doubled } };
                },
            );

        it('replaces a computed with a readonly computed', () => {
            overrideComponentSetup()(name, (previousState) => ({
                doubled: computed(() => previousState.count.value * 3),
            }));

            expect(mount(createDoubled()).get('.doubled').text()).toBe('3');
        });

        it('replaces a computed with a writable computed without syncing them', async () => {
            overrideComponentSetup()(name, (previousState) => ({
                doubled: computed({
                    get: () => previousState.count.value * 4,
                    set: (value: number) => {
                        previousState.count.value = value / 4;
                    },
                }),
            }));

            const wrapper = mount(createDoubled());
            expect(wrapper.get('.doubled').text()).toBe('4');

            await wrapper.get('button').trigger('click');
            expect(wrapper.get('.doubled').text()).toBe('10');
        });

        it('replaces a ref with a computed', () => {
            overrideComponentSetup()(name, () => ({ count: computed(() => 99) }));

            const component = defineExtendable({ name, template: '<div>{{ count }}</div>' }, () => ({
                public: { count: ref(1) },
            }));

            expect(mount(component).text()).toBe('99');
        });
    });

    describe('reactive objects', () => {
        const createGreeting = () =>
            defineExtendable(
                {
                    name,
                    template: '<div>{{ greeting.message }} {{ greeting.deep.value }}</div>',
                },
                () => ({
                    public: {
                        greeting: reactive({ message: 'Hello', deep: { value: 'base' } }),
                    },
                }),
            );

        it('replaces a reactive object', () => {
            overrideComponentSetup()(name, (previousState) => ({
                greeting: reactive({
                    message: `${previousState.greeting.message}!`,
                    deep: { value: 'override' },
                }),
            }));

            expect(mount(createGreeting()).text()).toBe('Hello! override');
        });

        it('warns in development when the replacement misses a nested key of the base object', () => {
            const warn = jest.spyOn(console, 'warn').mockImplementation(() => {});

            overrideComponentSetup()(name, () => ({
                greeting: reactive({ message: 'Hi', deep: { other: 'x' } }),
            }));

            mount(createGreeting());

            expect(warn).toHaveBeenCalledWith(
                '[sw-merge-rules] The override of "greeting" does not contain "greeting.deep.value".',
            );
            warn.mockRestore();
        });
    });

    describe('functions and plain values', () => {
        it('replaces a function', async () => {
            const calls: string[] = [];

            overrideComponentSetup()(name, (previousState) => ({
                save: () => {
                    previousState.save();
                    calls.push('override');
                },
            }));

            const wrapper = mount(
                defineExtendable({ name, template: '<button @click="save" />' }, () => ({
                    public: { save: () => calls.push('base') },
                })),
            );
            await wrapper.get('button').trigger('click');

            expect(calls).toEqual([
                'base',
                'override',
            ]);
        });

        it('replaces a binding with a primitive, as in the documented message example', () => {
            const error = jest.spyOn(console, 'error');

            overrideComponentSetup()(name, () => ({ message: 'Hello from the extension!' }));

            const wrapper = mount(
                defineExtendable({ name, template: '<div>{{ message }}</div>' }, () => ({
                    public: { message: 'Hello' },
                })),
            );

            expect(wrapper.text()).toBe('Hello from the extension!');
            expect(error).not.toHaveBeenCalled();
            error.mockRestore();
        });

        it('adds a key the base does not provide, and warns about it in development', () => {
            const warn = jest.spyOn(console, 'warn').mockImplementation(() => {});

            overrideComponentSetup()(name, () => ({ extra: ref('added') }));

            const wrapper = mount(
                defineExtendable({ name, template: '<div>{{ extra }}</div>' }, () => ({ public: { count: ref(1) } })),
            );

            expect(wrapper.text()).toBe('added');
            expect(warn).toHaveBeenCalledWith(
                '[sw-merge-rules] The override returns "extra", which the component does not provide.',
            );
            warn.mockRestore();
        });
    });
});
