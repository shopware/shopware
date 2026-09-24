/**
 * @sw-package framework
 * @group disabledCompat
 */
import { createCommentVNode, defineComponent, getCurrentInstance, h, reactive, resolveComponent } from 'vue';
import { flushPromises, mount } from '@vue/test-utils';
import { resetBlockIndex } from 'src/core/factory/twig-block-index';
import { setScriptSetupDataScope } from 'src/app/adapter/composition-extension-system/data-scope-helper';
import createDataScopeFixture from '../sw-block-override.spec/test-utils/create-data-scope-fixture';
import { legacyBlockHelpers } from './legacy-condition-context';
import { resetShimSlotState } from './twig-shim-layer';

type HostOptions = Record<string, unknown>;

async function mountHost(blockName: string, defaultContent: string, options: HostOptions = {}, attrs = {}) {
    return mount(
        {
            template: `<div class="host"><sw-block name="${blockName}">${defaultContent}</sw-block></div>`,
            ...options,
        },
        {
            attrs,
            global: {
                plugins: [createDataScopeFixture()],
                config: { globalProperties: legacyBlockHelpers as never },
                components: {
                    'sw-block': await wrapTestComponent('sw-block', { sync: true }),
                    'sw-block-parent': await wrapTestComponent('sw-block-parent', { sync: true }),
                },
            },
        },
    );
}

function overrideBlock(blockName: string, content: string) {
    Shopware.Component.override('sw-shim-host', {
        template: `{% block ${blockName} %}${content}{% endblock %}`,
    });
}

describe('app/component/structure/sw-block-override/shim/twig-shim-layer.ts: rendering in the host context', () => {
    beforeEach(() => {
        jest.spyOn(console, 'warn').mockImplementation(() => {});
    });

    afterEach(() => {
        jest.restoreAllMocks();
        resetBlockIndex();
        resetShimSlotState();
    });

    it('writes host state from an event handler', async () => {
        overrideBlock('shim_host_write', '<button class="open" @click="isOpen = true">open</button>');
        const wrapper = await mountHost('shim_host_write', '', { data: () => ({ isOpen: false }) });

        await wrapper.get('.open').trigger('click');

        expect((wrapper.vm as unknown as { isOpen: boolean }).isOpen).toBe(true);
    });

    it('binds v-model to host state', async () => {
        overrideBlock('shim_host_model', '<input class="name" v-model="name" /><span class="echo">{{ name }}</span>');
        const wrapper = await mountHost('shim_host_model', '', { data: () => ({ name: 'before' }) });

        await wrapper.get('.name').setValue('after');

        expect((wrapper.vm as unknown as { name: string }).name).toBe('after');
        expect(wrapper.get('.echo').text()).toBe('after');
    });

    it('registers template refs on the host', async () => {
        overrideBlock('shim_host_ref', '<input ref="pluginInput" class="plugin-input" />');
        const wrapper = await mountHost('shim_host_ref', '');

        expect(wrapper.vm.$refs.pluginInput).toBe(wrapper.get('.plugin-input').element);
    });

    it('emits events from the host', async () => {
        overrideBlock('shim_host_emit', `<button class="pick" @click="$emit('picked', 42)">pick</button>`);
        const onPicked = jest.fn();
        const wrapper = await mountHost('shim_host_emit', '', { emits: ['picked'] }, { onPicked });

        await wrapper.get('.pick').trigger('click');

        expect(onPicked).toHaveBeenCalledWith(42);
    });

    it('gives nested Twig blocks the host data scope', async () => {
        overrideBlock('shim_host_nested', '{% block shim_host_nested_inner %}<i class="inner">inner</i>{% endblock %}');
        const wrapper = await mountHost('shim_host_nested', '', { data: () => ({ label: 'from host' }) });
        const extension = mount(
            {
                template: `
                    <sw-block extends="shim_host_nested_inner" #default="{ label }">
                        <b class="extension">{{ label }}</b>
                    </sw-block>
                `,
            },
            { global: { components: { 'sw-block': await wrapTestComponent('sw-block', { sync: true }) } } },
        );
        await flushPromises();

        expect(wrapper.get('.extension').text()).toBe('from host');

        extension.unmount();
    });

    describe('native setup hosts', () => {
        function createSetupHost(blockName: string) {
            return defineComponent({
                setup() {
                    const state = reactive({ count: 0 });
                    setScriptSetupDataScope(getCurrentInstance()!, state);

                    return () =>
                        h(
                            resolveComponent('sw-block'),
                            { name: blockName, data: state },
                            {
                                default: () => [
                                    state.count > 0
                                        ? h('div', { class: 'positive' }, 'positive')
                                        : createCommentVNode('v-if'),
                                ],
                            },
                        );
                },
            });
        }

        async function mountSetupHost(blockName: string) {
            return mount(createSetupHost(blockName), {
                global: {
                    config: { globalProperties: legacyBlockHelpers as never },
                    components: {
                        'sw-block': await wrapTestComponent('sw-block', { sync: true }),
                        'sw-block-parent': await wrapTestComponent('sw-block-parent', { sync: true }),
                    },
                },
            });
        }

        it('reads and writes the setup state through the data scope', async () => {
            overrideBlock('shim_setup_state', '{% parent %}<button class="increment" @click="count++">{{ count }}</button>');
            const wrapper = await mountSetupHost('shim_setup_state');

            await wrapper.get('.increment').trigger('click');

            expect(wrapper.get('.increment').text()).toBe('1');
            expect(wrapper.find('.positive').exists()).toBe(true);
        });

        it('continues a v-if of the default content with a Twig v-else', async () => {
            overrideBlock('shim_setup_else', '{% parent %}<div v-else class="fallback" @click="count++">fallback</div>');
            const wrapper = await mountSetupHost('shim_setup_else');

            expect(wrapper.find('.positive').exists()).toBe(false);
            expect(wrapper.find('.fallback').exists()).toBe(true);

            await wrapper.get('.fallback').trigger('click');

            expect(wrapper.find('.positive').exists()).toBe(true);
            expect(wrapper.find('.fallback').exists()).toBe(false);
        });
    });
});
