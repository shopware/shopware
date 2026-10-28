/**
 * @sw-package framework
 */

import { flushPromises, shallowMount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { nextTick } from 'vue';
import VueAdapter from 'src/app/adapter/view/vue.adapter';
import AsyncComponentFactory from 'src/core/factory/async-component.factory';
import type { ComponentConfig } from 'src/core/factory/async-component.factory';
import { getBlockEntries } from 'src/core/factory/twig-block-index';
import { createApplication, prepareVueAdapterEnvironment, resetVueAdapterTestState } from './vue-adapter-test-setup';

type TestHmrRuntime = {
    createRecord: jest.Mock;
    rerender: jest.Mock;
};

describe('VueAdapter Twig HMR', () => {
    let vueAdapter: VueAdapter;
    let featureSpy: jest.SpyInstance;
    let wrapper: VueWrapper | undefined;
    let originalHmrRuntime: TestHmrRuntime | undefined;
    let testHmrRuntime: TestHmrRuntime;

    beforeEach(() => {
        prepareVueAdapterEnvironment();
        vueAdapter = new VueAdapter(createApplication());
        vueAdapter.componentFactory = AsyncComponentFactory;
        featureSpy = jest.spyOn(Shopware.Feature, 'isActive').mockImplementation((flag) => flag === 'ADMIN_VITE_8');

        originalHmrRuntime = (window as Window & { __VUE_HMR_RUNTIME__?: TestHmrRuntime }).__VUE_HMR_RUNTIME__;
        testHmrRuntime = {
            createRecord: jest.fn(),
            rerender: jest.fn(),
        };
        (window as Window & { __VUE_HMR_RUNTIME__?: TestHmrRuntime }).__VUE_HMR_RUNTIME__ = testHmrRuntime;
    });

    afterEach(() => {
        wrapper?.unmount();
        featureSpy.mockRestore();

        const target = window as Window & { __VUE_HMR_RUNTIME__?: TestHmrRuntime };
        if (originalHmrRuntime) {
            target.__VUE_HMR_RUNTIME__ = originalHmrRuntime;
        } else {
            delete target.__VUE_HMR_RUNTIME__;
        }

        resetVueAdapterTestState();
    });

    let componentConfig: ComponentConfig | boolean;

    it('replaces a Twig override and asks Vue to rerender the resolved component', async () => {
        const componentName = 'sw-hmr-template-test';

        AsyncComponentFactory.register(componentName, {
            template: '<div>{% block sw_hmr_template_test %}<p>Base content</p>{% endblock %}</div>',
            data() {
                return { draft: 'initial' };
            },
        });

        const hmrId = '/plugins/test/src/views/component.twig';

        const lazyComponentConfig = {
            default: {
                template: '{% block sw_hmr_template_test %}<p>Before update</p>{% endblock %}',
                __shopwareTwigHmrId: hmrId,
            },
        } as unknown as ComponentConfig;

        AsyncComponentFactory.override(componentName, () => Promise.resolve(lazyComponentConfig));

        componentConfig = await vueAdapter.componentResolver(componentName);
        expect(typeof componentConfig).not.toBe('boolean');
        if (typeof componentConfig === 'boolean') return;

        wrapper = shallowMount(componentConfig);
        (wrapper.vm.$data as { draft: string }).draft = 'unsaved';

        const applied = await vueAdapter.updateComponentTemplateFromHmr(
            componentName,
            hmrId,
            '{% block sw_hmr_template_test %}<p>After update</p>{% endblock %}',
        );
        await flushPromises();
        await nextTick();

        expect(applied).toBe(true);
        expect(testHmrRuntime.createRecord).toHaveBeenCalledWith(`shopware-component:${componentName}`, componentConfig);
        expect(testHmrRuntime.rerender).toHaveBeenCalledWith(`shopware-component:${componentName}`, expect.any(Function));
        expect(componentConfig.template).toContain('After update');
        expect((wrapper.vm.$data as { draft: string }).draft).toBe('unsaved');
    });

    it('declines HMR when the ADMIN_VITE_8 feature is inactive', async () => {
        featureSpy.mockReturnValue(false);

        await expect(
            vueAdapter.updateComponentTemplateFromHmr(
                'sw-missing-component',
                '/plugins/test/src/views/missing.twig',
                '<p>updated</p>',
            ),
        ).resolves.toBe(false);
    });

    it('preserves eager Twig blocks of other unresolved components during an update', async () => {
        const hmrId = '/plugins/test/active.twig';
        AsyncComponentFactory.register('sw-hmr-active', {
            template: '{% block sw_hmr_active %}<p>Base</p>{% endblock %}',
        });
        AsyncComponentFactory.override('sw-hmr-active', {
            template: '{% block sw_hmr_active %}<p>Before</p>{% endblock %}',
            __shopwareTwigHmrId: hmrId,
        });
        await vueAdapter.componentResolver('sw-hmr-active');

        AsyncComponentFactory.register('sw-hmr-unresolved', { template: '<div />' });
        AsyncComponentFactory.override('sw-hmr-unresolved', {
            template: '{% block sw_hmr_unresolved %}<p>Keep this native-block shim</p>{% endblock %}',
        });
        const entries = getBlockEntries('sw-hmr-unresolved', 'sw_hmr_unresolved');
        expect(entries).toHaveLength(1);

        await expect(
            vueAdapter.updateComponentTemplateFromHmr(
                'sw-hmr-active',
                hmrId,
                '{% block sw_hmr_active %}<p>After</p>{% endblock %}',
            ),
        ).resolves.toBe(true);

        expect(getBlockEntries('sw-hmr-unresolved', 'sw_hmr_unresolved')).toEqual(entries);
    });
});
