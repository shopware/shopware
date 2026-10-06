/**
 * @sw-package framework
 */

import { defineComponent } from 'vue';
import { mount } from '@vue/test-utils';
import 'src/app/store/block-override.store';
import { _overridesMap } from 'src/app/adapter/composition-extension-system';
import ProxyRefsOverride from './_mocks_/sw-proxy-refs-fixture.override.vue';
import ProxyRefsBase from './_mocks_/sw-proxy-refs-fixture.vue';

async function createWrapper() {
    // The hidden override component: mounted once, registers the callback and the block slot.
    const hidden = mount(ProxyRefsOverride, {
        global: {
            components: {
                'proxy-refs-wrapper': { template: '<div class="wrapper"><slot /></div>' },
                // Calls its function prop on click, i.e. outside the render that passed it.
                'proxy-refs-formatter': {
                    props: { format: { type: Function, required: true } },
                    data: () => ({ formatted: '' }),
                    template:
                        '<button class="formatter" type="button" @click="formatted = format(\'v\')">{{ formatted }}</button>',
                },
            },
        },
    });

    const wrapper = mount(
        defineComponent({
            components: { ProxyRefsBase },
            template: '<div><proxy-refs-base heading="First" /><proxy-refs-base heading="Second" /></div>',
        }),
    );

    await flushPromises();

    return { hidden, wrapper };
}

function texts(wrapper: ReturnType<typeof mount>, selector: string): string[] {
    return wrapper.findAll(selector).map((element) => element.text());
}

describe('experiment: override template bindings as block-scoped refs', () => {
    let hidden: ReturnType<typeof mount> | undefined;

    beforeEach(() => {
        delete _overridesMap['sw-proxy-refs-fixture'];
    });

    afterEach(() => {
        hidden?.unmount();
        delete _overridesMap['sw-proxy-refs-fixture'];
    });

    it('reads each base instance its own value in direct block content', async () => {
        const created = await createWrapper();
        hidden = created.hidden;

        expect(texts(created.wrapper, '.title')).toEqual(['FIRST', 'SECOND']);
        expect(texts(created.wrapper, '.direct')).toEqual(['First! 0', 'Second! 0']);
    });

    it('writes from a direct template handler into its own instance only', async () => {
        const created = await createWrapper();
        hidden = created.hidden;

        await created.wrapper.findAll('.direct')[0].trigger('click');
        await flushPromises();

        expect(texts(created.wrapper, '.direct')).toEqual(['First! 1', 'Second! 0']);
    });

    it('reads the right instance inside the slot of a child component', async () => {
        const created = await createWrapper();
        hidden = created.hidden;

        await created.wrapper.findAll('.direct')[1].trigger('click');
        await flushPromises();

        expect(texts(created.wrapper, '.nested')).toEqual(['0', '1']);
    });

    it('writes from a handler inside the slot of a child component into its own instance only', async () => {
        const created = await createWrapper();
        hidden = created.hidden;

        await created.wrapper.findAll('.nested-button')[1].trigger('click');
        await flushPromises();

        expect(texts(created.wrapper, '.direct')).toEqual(['First! 0', 'Second! 10']);
    });

    it('supports v-model per instance', async () => {
        const created = await createWrapper();
        hidden = created.hidden;

        await created.wrapper.findAll('.note')[0].setValue('hello');
        await flushPromises();

        expect(texts(created.wrapper, '.note-echo')).toEqual(['hello', '']);
    });

    it('calls a function binding of its own instance', async () => {
        const created = await createWrapper();
        hidden = created.hidden;

        await created.wrapper.findAll('.direct')[0].trigger('click');
        await created.wrapper.findAll('.direct')[1].trigger('click');
        await created.wrapper.findAll('.reset')[1].trigger('click');
        await flushPromises();

        expect(texts(created.wrapper, '.direct')).toEqual(['First! 1', 'Second! 0']);
    });

    it('reads a binding the override declared public', async () => {
        const created = await createWrapper();
        hidden = created.hidden;

        expect(texts(created.wrapper, '.public')).toEqual(['FIRST', 'SECOND']);
    });

    it('reads its own instance from an inline function prop the child calls later', async () => {
        const created = await createWrapper();
        hidden = created.hidden;

        await created.wrapper.findAll('.direct')[1].trigger('click');
        await created.wrapper.findAll('.formatter')[1].trigger('click');
        await flushPromises();

        expect(texts(created.wrapper, '.formatter')).toEqual(['', 'v x1']);
    });

    // Known limit: the active scope only lives through the synchronous part of a handler, so an inline
    // handler that writes after an `await` writes outside of its block.
    it('drops a write after an await inside an inline async handler and warns', async () => {
        const created = await createWrapper();
        hidden = created.hidden;
        const warn = jest.spyOn(console, 'warn').mockImplementation(() => {});

        await created.wrapper.findAll('.async')[0].trigger('click');
        await flushPromises();

        expect(texts(created.wrapper, '.direct')).toEqual(['First! 0', 'Second! 0']);
        expect(warn).toHaveBeenCalledWith('[sw-block] "clicks" was written outside of the block it belongs to.');

        // The late write must not have replaced the binding of the override component.
        await created.wrapper.findAll('.direct')[0].trigger('click');
        await flushPromises();

        expect(texts(created.wrapper, '.direct')).toEqual(['First! 1', 'Second! 0']);

        warn.mockRestore();
    });
});
