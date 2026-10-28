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
    let wrapper: VueWrapper | undefined;
    let originalHmrRuntime: TestHmrRuntime | undefined;
    let testHmrRuntime: TestHmrRuntime;

    beforeEach(() => {
        prepareVueAdapterEnvironment();
        vueAdapter = new VueAdapter(createApplication());
        vueAdapter.componentFactory = AsyncComponentFactory;

        originalHmrRuntime = (window as Window & { __VUE_HMR_RUNTIME__?: TestHmrRuntime }).__VUE_HMR_RUNTIME__;
        testHmrRuntime = {
            createRecord: jest.fn(),
            rerender: jest.fn(),
        };
        (window as Window & { __VUE_HMR_RUNTIME__?: TestHmrRuntime }).__VUE_HMR_RUNTIME__ = testHmrRuntime;
    });

    afterEach(() => {
        wrapper?.unmount();

        const target = window as Window & { __VUE_HMR_RUNTIME__?: TestHmrRuntime };
        if (originalHmrRuntime) {
            target.__VUE_HMR_RUNTIME__ = originalHmrRuntime;
        } else {
            delete target.__VUE_HMR_RUNTIME__;
        }

        resetVueAdapterTestState();
    });

    let componentConfig: ComponentConfig | boolean;

    it.activeFeatureFlags(['ADMIN_VITE_8'])(
        'replaces a Twig override and asks Vue to rerender the resolved component',
        async () => {
            const componentName = 'sw-hmr-template-test';

            AsyncComponentFactory.register(componentName, {
                template:
                    '<div><input v-model="draft">{% block sw_hmr_template_test %}<p>Base content</p>{% endblock %}</div>',
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
            await wrapper.find('input').setValue('unsaved');

            const applied = await vueAdapter.updateComponentTemplateFromHmr(
                componentName,
                hmrId,
                '{% block sw_hmr_template_test %}<p>After update</p>{% endblock %}',
            );
            await flushPromises();
            await nextTick();

            expect(applied).toBe(true);
            expect(testHmrRuntime.createRecord).toHaveBeenCalledWith(`shopware-component:${componentName}`, componentConfig);
            expect(testHmrRuntime.rerender).toHaveBeenCalledWith(
                `shopware-component:${componentName}`,
                expect.any(Function),
            );
            expect(componentConfig.template).toContain('After update');
            expect(wrapper.find('input').element.value).toBe('unsaved');
        },
    );

    it('declines HMR when the ADMIN_VITE_8 feature is inactive', async () => {
        await expect(
            vueAdapter.updateComponentTemplateFromHmr(
                'sw-missing-component',
                '/plugins/test/src/views/missing.twig',
                '<p>updated</p>',
            ),
        ).resolves.toBe(false);
    });

    it.activeFeatureFlags(['ADMIN_VITE_8'])(
        'preserves eager Twig blocks of other unresolved components during an update',
        async () => {
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
        },
    );
    it.activeFeatureFlags(['ADMIN_VITE_8'])(
        'refreshes resolved descendants when their inherited Twig override changes',
        async () => {
            const base = 'sw-hmr-inherited-base';
            const hmrId = '/plugins/test/inherited.twig';
            AsyncComponentFactory.register(base, {
                template: '<div>{% block inherited %}<span>Base</span>{% endblock %}</div>',
            });
            AsyncComponentFactory.override(base, {
                template: '{% block inherited %}{% parent %}<span>Before</span>{% endblock %}',
                __shopwareTwigHmrId: hmrId,
            });
            AsyncComponentFactory.extend('sw-hmr-child', base, {
                template: '{% block inherited %}{% parent %}<span>Child</span>{% endblock %}',
            });
            AsyncComponentFactory.extend('sw-hmr-grandchild', 'sw-hmr-child');
            const child = await vueAdapter.componentResolver('sw-hmr-child');
            const grandchild = await vueAdapter.componentResolver('sw-hmr-grandchild');
            expect(typeof child).not.toBe('boolean');
            expect(typeof grandchild).not.toBe('boolean');
            if (typeof child === 'boolean' || typeof grandchild === 'boolean') return;

            await expect(
                vueAdapter.updateComponentTemplateFromHmr(
                    base,
                    hmrId,
                    '{% block inherited %}{% parent %}<span>After</span>{% endblock %}',
                ),
            ).resolves.toBe(true);
            expect(child.template).toContain('After');
            expect(grandchild.template).toContain('After');
            expect(grandchild.template).toContain('Child');
            expect(testHmrRuntime.rerender).toHaveBeenCalledWith(
                'shopware-component:sw-hmr-grandchild',
                expect.any(Function),
            );
        },
    );

    it.activeFeatureFlags(['ADMIN_VITE_8'])(
        'keeps nested parent blocks and override order across consecutive edits',
        async () => {
            const name = 'sw-hmr-ordered';
            const hmrId = '/plugins/first/ordered.twig';
            AsyncComponentFactory.register(name, {
                template:
                    '<div>{% block outer %}<section>{% block inner %}<span>Base</span>{% endblock %}</section>{% endblock %}</div>',
            });
            AsyncComponentFactory.override(name, {
                template: '{% block inner %}{% parent %}<span>First</span>{% endblock %}',
                __shopwareTwigHmrId: hmrId,
            });
            AsyncComponentFactory.override(name, {
                template: '{% block inner %}{% parent %}<span>Second</span>{% endblock %}',
                __shopwareTwigHmrId: '/plugins/second/ordered.twig',
            });
            const config = await vueAdapter.componentResolver(name);
            if (typeof config === 'boolean') throw new Error('Component was not resolved');
            for (const value of ['Edited once', 'Edited twice', 'Edited three times']) {
                await expect(
                    vueAdapter.updateComponentTemplateFromHmr(
                        name,
                        hmrId,
                        `{% block inner %}{% parent %}<span>${value}</span>{% endblock %}`,
                    ),
                ).resolves.toBe(true);
                if (typeof config.template !== 'string') throw new Error('Component template was not built');
                expect(config.template).toContain('Base');
                expect(config.template).toContain(value);
                expect(config.template.indexOf(value)).toBeLessThan(config.template.indexOf('Second'));
                expect(config.template.match(/Second/g)).toHaveLength(1);
                expect(config.template).not.toContain('First');
            }
            expect(AsyncComponentFactory.getOverrideRegistry().get(name)).toHaveLength(2);
        },
    );

    it.activeFeatureFlags(['ADMIN_VITE_8'])(
        'uses an edited prebuilt template when its Vue component is first resolved',
        async () => {
            const name = 'sw-hmr-prebuilt';
            const hmrId = '/plugins/test/prebuilt.twig';
            AsyncComponentFactory.register(name, { template: '<div>{% block prebuilt %}Before{% endblock %}</div>' });
            AsyncComponentFactory.override(name, {
                template: '{% block prebuilt %}Override{% endblock %}',
                __shopwareTwigHmrId: hmrId,
            });
            await AsyncComponentFactory.build(name);
            await expect(
                vueAdapter.updateComponentTemplateFromHmr(name, hmrId, '{% block prebuilt %}After{% endblock %}'),
            ).resolves.toBe(true);
            const config = await vueAdapter.componentResolver(name);
            expect(config).toHaveProperty('template', expect.stringContaining('After'));
        },
    );
    it.activeFeatureFlags(['ADMIN_VITE_8'])('recovers after a malformed Twig override is repaired', async () => {
        const name = 'sw-hmr-recovery';
        const hmrId = '/plugins/test/recovery.twig';
        AsyncComponentFactory.register(name, { template: '<div>{% block recovery %}Base{% endblock %}</div>' });
        AsyncComponentFactory.override(name, {
            template: '{% block recovery %}Before{% endblock %}',
            __shopwareTwigHmrId: hmrId,
        });
        const config = await vueAdapter.componentResolver(name);
        const logError = jest.spyOn(console, 'error').mockImplementation(() => {});
        const logWarning = jest.spyOn(console, 'warn').mockImplementation(() => {});
        try {
            await expect(
                vueAdapter.updateComponentTemplateFromHmr(name, hmrId, '{% block recovery %}Broken'),
            ).rejects.toThrow();
            expect(config).toHaveProperty('template', expect.stringContaining('Before'));
            testHmrRuntime.rerender.mockClear();
            await expect(
                vueAdapter.updateComponentTemplateFromHmr(name, hmrId, '{% block recovery %}Repaired{% endblock %}'),
            ).resolves.toBe(true);
            expect(config).toHaveProperty('template', expect.stringContaining('Repaired'));
            expect(testHmrRuntime.rerender).toHaveBeenCalledTimes(1);
        } finally {
            logError.mockRestore();
            logWarning.mockRestore();
        }
    });
});
