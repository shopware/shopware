/**
 * @sw-package framework
 */
import { flushPromises, mount } from '@vue/test-utils';
import VueAdapter from '../vue.adapter';
import LocaleFactory from 'src/core/factory/locale.factory';
import DirectiveFactory from 'src/core/factory/directive.factory';
import FilterFactory from 'src/core/factory/filter.factory';
import ModuleFactory from 'src/core/factory/module.factory';
import AsyncComponentFactory from 'src/core/factory/async-component.factory';
import { createApplication, prepareVueAdapterEnvironment, resetVueAdapterTestState } from './vue-adapter-test-setup';

const deferredComponents = [
    'sw-settings-document-detail',
    'sw-settings-product-feature-sets-detail',
    'sw-settings-search-searchable-content',
    'sw-sales-channel-products-assignment-single-products',
    'sw-sales-channel-product-assignment-categories',
    'sw-sales-channel-products-assignment-dynamic-product-groups',
];

describe('deferred non-shell components', () => {
    beforeEach(() => {
        prepareVueAdapterEnvironment();
    });

    afterEach(() => {
        resetVueAdapterTestState();
    });

    it.each(deferredComponents)('loads %s and its extension override only on first render', async (name) => {
        const registry = Shopware.Component.getComponentRegistry();
        const overrides = Shopware.Component.getOverrideRegistry();
        const original = registry.get(name);
        const originalOverrides = overrides.get(name);
        registry.delete(name);
        overrides.delete(name);
        const loadDefinition = jest.fn(() =>
            Promise.resolve({
                template: '<p>{{ label }}</p>',
                data: () => ({ label: 'Base definition' }),
            }),
        );
        const loadOverride = jest.fn(() =>
            Promise.resolve({
                data() {
                    return { label: 'Extension override loaded' };
                },
            }),
        );
        AsyncComponentFactory.register(name, loadDefinition);
        Shopware.Component.override(name, loadOverride);

        const application = createApplication()
            .addFactory('locale', () => LocaleFactory)
            .addFactory('directive', () => DirectiveFactory)
            .addFactory('filter', () => FilterFactory)
            .addFactory('component', () => AsyncComponentFactory)
            .addFactory('module', () => ModuleFactory);
        application.addInitializer('router', () => ({ addModuleRoutes: jest.fn(), createRouterInstance: jest.fn() }));
        const adapter = new VueAdapter(application);
        try {
            await adapter.initDependencies();
            expect(loadDefinition).not.toHaveBeenCalled();
            expect(loadOverride).not.toHaveBeenCalled();
            const component = adapter.getWrapper().component(name);
            if (!component) throw new Error(`Component ${name} was not registered`);
            const wrapper = mount({ components: { Deferred: component }, template: '<Deferred />' });
            try {
                expect(wrapper.text()).toBe('');
                expect(loadDefinition).toHaveBeenCalled();
                await flushPromises();
                expect(loadOverride).toHaveBeenCalled();
                expect(wrapper.text()).toBe('Extension override loaded');
            } finally {
                wrapper.unmount();
            }
        } finally {
            registry.delete(name);
            overrides.delete(name);
            if (original) registry.set(name, original);
            if (originalOverrides) overrides.set(name, originalOverrides);
        }
    });
});
