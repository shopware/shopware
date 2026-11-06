/**
 * @sw-package framework
 */
import VueAdapter from '../vue.adapter';
import { mount } from '@vue/test-utils';
import { computed, defineComponent, h, nextTick, ref } from 'vue';
import type { SetupContext } from 'vue';
import { createExtendableSetup } from '../../composition-extension-system';
import ComponentFactory from 'src/core/factory/async-component.factory';
import { createApplication, prepareVueAdapterEnvironment, resetVueAdapterTestState } from './vue-adapter-test-setup';
import type { ComponentConfig, SetupHmrMetadata } from 'src/core/factory/async-component.factory';

describe('native SFC HMR identities', () => {
    beforeEach(prepareVueAdapterEnvironment);
    afterEach(resetVueAdapterTestState);

    it.activeFeatureFlags(['ADMIN_VITE_8'])('preserves the Vue compiler HMR record for a native component', async () => {
        const config = { name: 'sw-native-identity', render: jest.fn(), __hmrId: 'native-compiler-id' };
        jest.spyOn(Shopware.Component, 'build').mockResolvedValueOnce(config as ComponentConfig);
        const adapter = new VueAdapter(createApplication());
        expect(await adapter.componentResolver(config.name)).toHaveProperty('__hmrId', 'native-compiler-id');
    });

    it.activeFeatureFlags(['ADMIN_VITE_8']).each(['script', 'template'])(
        'updates cached native %s definitions while all instances are unmounted',
        async (kind) => {
            const name = `sw-native-cached-${kind}`;
            const data: NonNullable<SetupHmrMetadata['data']> = {};
            const setupFor = (key: string) => (props: Record<string, unknown>, context: SetupContext) =>
                createExtendableSetup({ name, props, context }, () => ({
                    public: { value: computed(() => String(props[key])) },
                }));
            const config = {
                name,
                __hmrId: `native-cached-id:${kind}`,
                props: ['before'],
                setup: setupFor('before'),
                render(this: { value: string }) {
                    return h('p', this.value);
                },
                __shopwareSetupHmr: {
                    componentName: name,
                    mode: 'base' as const,
                    version: '1',
                    invalidate: jest.fn(),
                    data,
                },
            };
            ComponentFactory.register(name, config);
            const runtime = (
                globalThis as typeof globalThis & {
                    __VUE_HMR_RUNTIME__: {
                        createRecord: (id: string, component: object) => boolean;
                        reload: (id: string, component: object) => void;
                        rerender: (id: string, render: unknown) => void;
                    };
                }
            ).__VUE_HMR_RUNTIME__;
            runtime.createRecord(config.__hmrId, config);
            const adapter = new VueAdapter(createApplication());
            const cached = await adapter.componentResolver(name);
            if (typeof cached === 'boolean') throw new Error('Expected a native component');
            const visible = ref(true);
            const Parent = defineComponent({
                render: () => h('main', visible.value ? [h(cached, { before: 'Before', after: 'After' })] : []),
            });
            const wrapper = mount(Parent);
            try {
                expect(wrapper.text()).toBe('Before');
                visible.value = false;
                await nextTick();
                const templateRender = () => h('p', 'Template after');
                const next =
                    kind === 'script'
                        ? { ...config, props: ['after'], setup: setupFor('after') }
                        : { ...config, render: templateRender };
                data.updateDefinitions?.(next);
                if (kind === 'script') runtime.reload(config.__hmrId, next);
                else runtime.rerender(config.__hmrId, templateRender);
                expect(await adapter.componentResolver(name)).toBe(cached);
                visible.value = true;
                await nextTick();
                expect(wrapper.text()).toBe(kind === 'script' ? 'After' : 'Template after');
            } finally {
                wrapper.unmount();
            }
        },
    );

    it.activeFeatureFlags(['ADMIN_VITE_8']).each([false, true])(
        'requests a page reload only for a factory-composed native definition (inherited: %s)',
        async (inherited) => {
            const data: { reloadPage?: boolean } = {};
            const config = {
                name: 'sw-native-composed',
                render: jest.fn(),
                __hmrId: 'native-composed-id',
                __shopwareSetupHmr: {
                    componentName: 'sw-native-composed',
                    mode: 'base' as const,
                    version: '1',
                    invalidate: jest.fn(),
                    data,
                },
                ...(inherited ? { extends: { name: 'sw-native-parent' } } : {}),
            };
            jest.spyOn(Shopware.Component, 'build').mockResolvedValueOnce(config as ComponentConfig);
            const adapter = new VueAdapter(createApplication());
            await adapter.componentResolver(config.name);
            expect(data.reloadPage).toBe(inherited);
        },
    );
});
