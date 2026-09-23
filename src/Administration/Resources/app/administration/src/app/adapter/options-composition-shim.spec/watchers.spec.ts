/**
 * @sw-package framework
 */

import { nextTick, ref, watch } from 'vue';
import { mount } from '@vue/test-utils';
import { _overridesMap } from 'src/app/adapter/composition-extension-system';
import { defineExtendable, registerOptionsOverride } from './test-utils';

const name = 'sw-shim-watchers';

const createCounter = (onBaseChange?: (value: number) => void) =>
    defineExtendable({ name, template: '<div>{{ count }}</div><button @click="increment" />' }, () => {
        const count = ref(0);
        const increment = () => {
            count.value += 1;
        };

        if (onBaseChange) {
            watch(count, onBaseChange);
        }

        return { public: { count, increment } };
    });

describe('src/app/adapter/options-composition-shim: watch', () => {
    beforeEach(() => {
        _overridesMap.clear();
    });

    it('calls a function watcher with the new and old value', async () => {
        const handler = jest.fn();
        registerOptionsOverride(name, { watch: { count: handler } });

        const wrapper = mount(createCounter());
        await wrapper.get('button').trigger('click');

        expect(handler).toHaveBeenCalledWith(1, 0);
    });

    it('supports an object watcher with immediate, deep and flush', () => {
        const calls: string[] = [];
        registerOptionsOverride(name, {
            watch: {
                count: {
                    handler(value: number) {
                        calls.push(`count:${value}`);
                    },
                    immediate: true,
                    deep: true,
                    flush: 'sync',
                },
            },
        });

        const wrapper = mount(createCounter());
        expect(calls).toEqual(['count:0']);

        (wrapper.vm as unknown as { count: number }).count = 5;
        expect(calls).toEqual([
            'count:0',
            'count:5',
        ]);
    });

    it('calls a method named by a string watcher', async () => {
        const handler = jest.fn();
        registerOptionsOverride(name, {
            watch: { count: 'onCountChange' },
            methods: { onCountChange: handler },
        });

        const wrapper = mount(createCounter());
        await wrapper.get('button').trigger('click');

        expect(handler).toHaveBeenCalledWith(1, 0);
    });

    it('logs an error when a string watcher names no method', async () => {
        registerOptionsOverride(name, { watch: { count: 'missing' } });
        const error = jest.spyOn(console, 'error').mockImplementation(() => {});
        const warn = jest.spyOn(console, 'warn').mockImplementation(() => {});

        const wrapper = mount(createCounter());
        await wrapper.get('button').trigger('click');

        expect(error).toHaveBeenCalledWith(
            '[Options API Shim] Watch handler "missing" is not a function or does not exist on the component.',
        );
        error.mockRestore();
        warn.mockRestore();
    });

    it('supports an array of handlers', async () => {
        const first = jest.fn();
        const second = jest.fn();
        registerOptionsOverride(name, { watch: { count: [first, { handler: second }] } });

        const wrapper = mount(createCounter());
        await wrapper.get('button').trigger('click');

        expect(first).toHaveBeenCalledWith(1, 0);
        expect(second).toHaveBeenCalledWith(1, 0);
    });

    it('skips a dot-notation path with a warning and keeps the other watchers', async () => {
        const handler = jest.fn();
        registerOptionsOverride(name, { watch: { 'entity.name': jest.fn(), count: handler } });
        const warn = jest.spyOn(console, 'warn').mockImplementation(() => {});

        const wrapper = mount(createCounter());
        await wrapper.get('button').trigger('click');

        expect(warn).toHaveBeenCalledWith(expect.stringContaining('Dot-notation watch path "entity.name" is not supported'));
        expect(handler).toHaveBeenCalledTimes(1);
        warn.mockRestore();
    });

    it('keeps the watchers of the base component', async () => {
        const base = jest.fn();
        const override = jest.fn();
        registerOptionsOverride(name, { watch: { count: override } });

        const wrapper = mount(createCounter(base));
        await wrapper.get('button').trigger('click');

        expect(base).toHaveBeenCalledTimes(1);
        expect(override).toHaveBeenCalledTimes(1);
    });

    it('stops its watchers when the component unmounts', async () => {
        const handler = jest.fn();
        const source = ref(0);
        registerOptionsOverride(name, {
            computed: {
                external: () => source.value,
            },
            watch: { external: handler },
        });

        const wrapper = mount(createCounter());
        wrapper.unmount();
        source.value += 1;
        await nextTick();

        expect(handler).not.toHaveBeenCalled();
    });
});
