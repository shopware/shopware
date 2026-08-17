/**
 * @sw-package framework
 * @group disabledCompat
 *
 * Twig → Native Block shim, end to end: an extension extends a component that is still written as
 * `index.js` + `.html.twig` with a native `<sw-block extends>` override.
 */

import { mount } from '@vue/test-utils';
import ComponentFactory from 'src/core/factory/async-component.factory';
import TemplateFactory from 'src/core/factory/template.factory';
import * as twigBlockIndex from 'src/core/factory/twig-block-index';
import { registerNativeBlockOverrides, resetNativeBlockOverrides } from 'src/core/factory/native-block-override-registry';
import createDataScopeFixture from 'src/app/component/structure/sw-block-override/sw-block-override.spec/test-utils/create-data-scope-fixture';
import blockOverrideStore from 'src/app/store/block-override.store';

/**
 * A component in the shape everything in core still has today: Options API plus a Twig template.
 */
function registerLegacyComponent(template) {
    ComponentFactory.register('sw-legacy-demo', {
        template,
        data() {
            return {
                headline: 'from the legacy component',
            };
        },
    });
}

/**
 * Mounts the legacy component next to a native `<sw-block extends>` override, the way the hidden
 * override container in `sw-admin` does at boot.
 */
async function mountWithNativeOverride(overrideTemplate) {
    const swBlock = (await import('src/app/component/structure/sw-block-override/sw-block/index')).default;
    const swBlockParent = (await import('src/app/component/structure/sw-block-override/sw-block-parent/index')).default;
    const legacyComponent = await ComponentFactory.build('sw-legacy-demo');

    return mount(
        {
            template: `<div class="test-root">
                <sw-legacy-demo />
                ${overrideTemplate}
            </div>`,
        },
        {
            global: {
                components: {
                    'sw-legacy-demo': legacyComponent,
                    'sw-block': swBlock,
                    'sw-block-parent': swBlockParent,
                },
                plugins: [createDataScopeFixture()],
            },
        },
    );
}

describe('core/factory/async-component.factory.spec/legacy-twig-native-block-shim', () => {
    beforeAll(() => {
        Shopware.Store.register('blockOverride', blockOverrideStore);
    });

    beforeEach(() => {
        ComponentFactory.getComponentRegistry().clear();
        ComponentFactory.getOverrideRegistry().clear();
        ComponentFactory._clearComponentHelper();
        TemplateFactory.getTemplateRegistry().clear();
        TemplateFactory.getNormalizedTemplateRegistry().clear();
        TemplateFactory.disableTwigCache();
        ComponentFactory.markComponentTemplatesAsNotResolved();
        twigBlockIndex.resetBlockIndex();
        resetNativeBlockOverrides();
    });

    it('renders a native override into a block of a Twig component', async () => {
        registerNativeBlockOverrides(['sw_legacy_demo_content']);
        registerLegacyComponent(
            '<div class="legacy-root">{% block sw_legacy_demo_content %}<div class="default"></div>{% endblock %}</div>',
        );

        const wrapper = await mountWithNativeOverride(
            '<sw-block extends="sw_legacy_demo_content"><div class="native-injected"></div></sw-block>',
        );
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.legacy-root .native-injected').exists()).toBe(true);
        // An override without <sw-block-parent /> replaces the block, exactly as in a native component.
        expect(wrapper.find('.default').exists()).toBe(false);
    });

    it('renders the default block content through sw-block-parent', async () => {
        registerNativeBlockOverrides(['sw_legacy_demo_content']);
        registerLegacyComponent(
            '<div class="legacy-root">{% block sw_legacy_demo_content %}<div class="default"></div>{% endblock %}</div>',
        );

        const wrapper = await mountWithNativeOverride(
            `<sw-block extends="sw_legacy_demo_content">
                <sw-block-parent />
                <div class="native-injected"></div>
            </sw-block>`,
        );
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.legacy-root .default').exists()).toBe(true);
        expect(wrapper.find('.legacy-root .native-injected').exists()).toBe(true);
    });

    it('exposes the Options API instance of the Twig component as the block data scope', async () => {
        registerNativeBlockOverrides(['sw_legacy_demo_content']);
        registerLegacyComponent(
            '<div class="legacy-root">{% block sw_legacy_demo_content %}<div class="default"></div>{% endblock %}</div>',
        );

        const wrapper = await mountWithNativeOverride(
            `<sw-block extends="sw_legacy_demo_content">
                <template #default="{ headline }"><div class="native-injected">{{ headline }}</div></template>
            </sw-block>`,
        );
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.native-injected').text()).toBe('from the legacy component');
    });

    it('renders a legacy Twig override exactly once next to a native one', async () => {
        registerNativeBlockOverrides(['sw_legacy_demo_content']);
        registerLegacyComponent(
            '<div class="legacy-root">{% block sw_legacy_demo_content %}<div class="default"></div>{% endblock %}</div>',
        );
        ComponentFactory.override('sw-legacy-demo', {
            template: '{% block sw_legacy_demo_content %}{% parent %}<div class="from-twig-override"></div>{% endblock %}',
        });

        const wrapper = await mountWithNativeOverride(
            `<sw-block extends="sw_legacy_demo_content">
                <sw-block-parent />
                <div class="native-injected"></div>
            </sw-block>`,
        );
        await wrapper.vm.$nextTick();

        // The Twig override is merged into the template by TemplateFactory. Consuming the Twig block
        // index on top of that would render it a second time.
        expect(wrapper.findAll('.from-twig-override')).toHaveLength(1);
        expect(wrapper.findAll('.default')).toHaveLength(1);
        expect(wrapper.findAll('.native-injected')).toHaveLength(1);
    });

    it('leaves a Twig component untouched when no native override targets it', async () => {
        registerLegacyComponent(
            '<div class="legacy-root">{% block sw_legacy_demo_content %}<div class="default"></div>{% endblock %}</div>',
        );

        const wrapper = await mountWithNativeOverride('');
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.legacy-root .default').exists()).toBe(true);
        expect(wrapper.findComponent({ name: 'sw-block' }).exists()).toBe(false);
    });
});
