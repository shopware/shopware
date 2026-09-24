/**
 * @sw-package framework
 */

/* eslint-disable @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-call */

import { computed, createApp, ref, watch, nextTick } from 'vue';
import { mount } from '@vue/test-utils';
import { _overridesMap, overrideComponentSetup } from 'src/app/adapter/composition-extension-system';
import { defineExtendable } from './test-utils';

const name = 'sw-override-chain';

const createCounter = () =>
    defineExtendable(
        {
            name,
            template:
                '<div class="count">{{ count }}</div><div class="label">{{ label }}</div><button @click="increment" />',
        },
        () => {
            const count = ref(1);
            const label = computed(() => `Count ${count.value}`);
            const increment = () => {
                count.value += 1;
            };

            return { public: { count, label, increment } };
        },
    );

describe('src/app/adapter/composition-extension-system: several overrides of one component', () => {
    beforeEach(() => {
        _overridesMap.clear();
    });

    it('applies overrides in registration order, each one seeing the result of the previous ones', () => {
        overrideComponentSetup()(name, (previousState) => ({
            label: computed(() => `${previousState.label.value} (A)`),
        }));
        overrideComponentSetup()(name, (previousState) => ({
            label: computed(() => `${previousState.label.value} (B)`),
        }));

        expect(mount(createCounter()).get('.label').text()).toBe('Count 1 (A) (B)');
    });

    it('chains function overrides through the previous state', async () => {
        overrideComponentSetup()(name, (previousState) => ({
            increment: () => {
                previousState.increment();
                previousState.count.value *= 10;
            },
        }));
        overrideComponentSetup()(name, (previousState) => ({
            increment: () => {
                previousState.increment();
                previousState.count.value += 3;
            },
        }));

        const wrapper = mount(createCounter());
        await wrapper.get('button').trigger('click');

        expect(wrapper.get('.count').text()).toBe('23');
    });

    it('syncs several ref overrides of the same key', async () => {
        const first = ref(0);
        const second = ref(0);
        overrideComponentSetup()(name, () => ({ count: first }));
        overrideComponentSetup()(name, () => ({ count: second }));

        const wrapper = mount(createCounter());
        await wrapper.get('button').trigger('click');

        expect(wrapper.get('.count').text()).toBe('1');
        expect(first.value).toBe(1);
        expect(second.value).toBe(1);
    });

    it('applies overrides to every instance separately', async () => {
        overrideComponentSetup()(name, (previousState) => ({
            increment: () => {
                previousState.count.value += 10;
            },
        }));

        const first = mount(createCounter());
        const second = mount(createCounter());
        await first.get('button').trigger('click');

        expect(first.get('.count').text()).toBe('11');
        expect(second.get('.count').text()).toBe('1');
    });

    it('does not apply an override to instances that were set up before it was registered', () => {
        const wrapper = mount(createCounter());

        overrideComponentSetup()(name, () => ({ count: ref(5) }));

        expect(wrapper.get('.count').text()).toBe('1');
        expect(mount(createCounter()).get('.count').text()).toBe('5');
    });

    it('disposes watchers created by an override when the component unmounts', async () => {
        const source = ref(0);
        const watcher = jest.fn();

        overrideComponentSetup()(name, () => {
            watch(source, watcher);

            return {};
        });

        const wrapper = mount(createCounter());
        source.value += 1;
        await nextTick();
        expect(watcher).toHaveBeenCalledTimes(1);

        wrapper.unmount();
        source.value += 1;
        await nextTick();
        expect(watcher).toHaveBeenCalledTimes(1);
    });

    it('reports a failing override through Vue and still applies the others', () => {
        const errors: unknown[] = [];

        overrideComponentSetup()(name, () => {
            throw new Error('broken override');
        });
        overrideComponentSetup()(name, () => ({ count: ref(100) }));

        // Mounted without test-utils, which rethrows every error reported while mounting.
        const app = createApp(createCounter());
        app.config.errorHandler = (error: unknown) => {
            errors.push(error);
        };
        const root = document.createElement('div');
        app.mount(root);

        expect(errors).toEqual([new Error('broken override')]);
        expect(root.querySelector('.count')?.textContent).toBe('100');
        app.unmount();
    });
});
