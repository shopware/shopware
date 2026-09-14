/** @sw-package framework */
import { mount, flushPromises } from '@vue/test-utils';
import { h, nextTick, ref } from 'vue';
import { createRouter, createMemoryHistory, RouterView } from 'vue-router';
import metaInfoPlugin from 'src/app/plugin/meta-info.plugin';
import shortcutPlugin from 'src/app/plugin/shortcut.plugin';
import { prepareLegacyComponent } from './component-definition';
import { createExtendableSetup } from '../composition-extension-system';
import type { ComponentConfig, IndexedAwaitedComponentConfig } from 'src/core/factory/async-component.factory';

function prepare(options: ComponentConfig) {
    return prepareLegacyComponent(
        'bridge-plugin-host',
        {
            __swExtendable: true,
            setup(props, context) {
                return createExtendableSetup({ name: 'bridge-plugin-host', props, context }, () => ({
                    public: { title: ref('base') },
                }));
            },
            render(this: { title: string }) {
                return h('div', this.title);
            },
        },
        [{ resolvedConfig: options } as IndexedAwaitedComponentConfig],
    );
}

describe('native Options plugin integration', () => {
    afterEach(() => {
        jest.restoreAllMocks();
        metaInfoPlugin.pluginInstalled = false;
    });

    it('updates the title from legacy metaInfo and stops its effect on unmount', async () => {
        metaInfoPlugin.pluginInstalled = false;
        const wrapper = mount(
            prepare({
                metaInfo(this: { title: string }) {
                    return { title: this.title };
                },
            }),
            {
                global: { plugins: [metaInfoPlugin] },
            },
        );
        const vm = wrapper.vm as unknown as { title: string };
        expect(document.title).toBe('base');
        vm.title = 'changed';
        await nextTick();
        expect(document.title).toBe('changed');
        wrapper.unmount();
        vm.title = 'unmounted';
        await nextTick();
        expect(document.title).toBe('changed');
    });

    it('dispatches shortcuts through legacy methods and releases them on unmount', async () => {
        const saved = jest.fn();
        const wrapper = mount(prepare({ shortcuts: { s: 'save' }, methods: { save: saved } }), {
            attachTo: document.body,
            global: { plugins: [shortcutPlugin] },
        });
        await wrapper.trigger('keydown', { key: 's' });
        expect(saved).toHaveBeenCalledTimes(1);
        wrapper.unmount();
        document.body.dispatchEvent(new KeyboardEvent('keydown', { key: 's', bubbles: true }));
        expect(saved).toHaveBeenCalledTimes(1);
    });

    it('executes native route enter, update and leave guards on the actual host instance', async () => {
        const entered = jest.fn<void, [unknown]>();
        const updated = jest.fn();
        const left = jest.fn();
        const host = prepare({
            beforeRouteEnter(_to, _from, next) {
                next((vm) => entered(vm));
            },
            beforeRouteUpdate(this: { title: string }) {
                updated(this.title);
            },
            beforeRouteLeave(this: { title: string }) {
                left(this.title);
            },
        });
        const router = createRouter({
            history: createMemoryHistory(),
            routes: [
                { path: '/host/:id', component: host },
                { path: '/other', component: { render: () => h('span', 'other') } },
            ],
        });
        await router.push('/host/1');
        await router.isReady();
        const wrapper = mount({ render: () => h(RouterView) }, { global: { plugins: [router] } });
        await flushPromises();
        expect(entered).toHaveBeenCalledTimes(1);
        expect(entered.mock.calls[0][0]).toHaveProperty('title', 'base');
        await router.push('/host/2');
        expect(updated).toHaveBeenCalledWith('base');
        await router.push('/other');
        expect(left).toHaveBeenCalledWith('base');
        expect(wrapper.text()).toBe('other');
        wrapper.unmount();
    });
});
