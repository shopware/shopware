/**
 * @sw-package framework
 * @group disabledCompat
 */

/**
 * End-to-end coverage of the Native → Twig Extension Bridge: a real `.override.vue` file, compiled by
 * the Jest Vue transformer, extending a component that still uses a `.html.twig` template and the
 * Options API.
 *
 * Both channels are exercised at once, which is the point of the bridge - the template channel gives the
 * extension its rendering position, the setup channel gives it the base's state.
 */

import { mount } from '@vue/test-utils';
import { _overridesMap } from 'src/app/adapter/composition-extension-system';
import TemplateFactory from 'src/core/factory/template.factory';
import { resetNativeBlockHosts } from 'src/core/factory/native-extensions-twig-bridge';
import swBlock from 'src/app/component/structure/sw-block-override/sw-block/index';
import swBlockParent from 'src/app/component/structure/sw-block-override/sw-block-parent/index';
import swNativeBlockHost from 'src/app/component/structure/sw-block-override/sw-native-block-host/index';
import createDataScopeFixture from 'src/app/component/structure/sw-block-override/sw-block-override.spec/test-utils/create-data-scope-fixture';
import BridgeFixtureOverride from './_mocks_/sw-jest-bridge-fixture.override.vue';

const COMPONENT_NAME = 'sw-jest-bridge-fixture';
const TEMPLATE = `{% block sw_jest_bridge_fixture_content %}<div class="legacy-content">{{ headline }}</div>{% endblock %}`;
const UNTARGETED_COMPONENT_NAME = 'sw-jest-bridge-untargeted';
const UNTARGETED_TEMPLATE = `{% block sw_jest_bridge_untargeted_content %}<div class="legacy-content">{{ headline }}</div>{% endblock %}`;

const globalMountOptions = {
    components: {
        'sw-block': swBlock,
        'sw-block-parent': swBlockParent,
        'sw-native-block-host': swNativeBlockHost,
    },
};

/**
 * Registers the not-yet-migrated component the override targets: a Twig template plus Options API state.
 */
function registerLegacyComponent(): void {
    /** @private test fixture component */
    Shopware.Component.register(COMPONENT_NAME, () =>
        Promise.resolve({
            template: TEMPLATE,
            data() {
                return { headline: 'Legacy' };
            },
        }),
    );
}

/**
 * Registers a second legacy component that no override targets, as the control case.
 */
function registerUntargetedLegacyComponent(): void {
    /** @private test fixture component */
    Shopware.Component.register(UNTARGETED_COMPONENT_NAME, () =>
        Promise.resolve({
            template: UNTARGETED_TEMPLATE,
            data() {
                return { headline: 'Legacy' };
            },
        }),
    );
}

function cleanUpRegistries(): void {
    [
        COMPONENT_NAME,
        UNTARGETED_COMPONENT_NAME,
    ].forEach((componentName) => {
        Shopware.Component.getComponentRegistry().delete(componentName);
        Shopware.Component.getOverrideRegistry().delete(componentName);
        TemplateFactory.getTemplateRegistry().delete(componentName);
        TemplateFactory.getNormalizedTemplateRegistry().delete(componentName);
    });

    TemplateFactory.clearTwigCache();
    resetNativeBlockHosts();
}

describe('Native → Twig Extension Bridge (integrative)', () => {
    beforeEach(() => {
        cleanUpRegistries();
    });

    afterEach(() => {
        cleanUpRegistries();
        delete _overridesMap[COMPONENT_NAME];
    });

    it('renders a native extension inside a component that still uses a Twig template', async () => {
        registerLegacyComponent();

        const config = await Shopware.Component.build(COMPONENT_NAME);

        // The override component registers its <sw-block extends> slot and its setup override on mount.
        mount(BridgeFixtureOverride, { global: globalMountOptions });

        const wrapper = mount(config, { global: { ...globalMountOptions, plugins: [createDataScopeFixture()] } });

        await flushPromises();

        // Setup channel: the override reads the converted Options API state and replaces it.
        expect(wrapper.get('.legacy-content').text()).toBe('Legacy!');
        // Template channel: the extension renders after the block's original content.
        expect(wrapper.get('.native-extension').text()).toBe('Legacy!');
        expect(wrapper.find('.legacy-content + .native-extension').exists()).toBe(true);
    });

    it('leaves a legacy component no override targets untouched', async () => {
        registerUntargetedLegacyComponent();

        const config = await Shopware.Component.build(UNTARGETED_COMPONENT_NAME);

        mount(BridgeFixtureOverride, { global: globalMountOptions });

        const wrapper = mount(config, { global: { ...globalMountOptions, plugins: [createDataScopeFixture()] } });

        await flushPromises();

        expect(wrapper.get('.legacy-content').text()).toBe('Legacy');
        expect(wrapper.find('.native-extension').exists()).toBe(false);
        expect(wrapper.html()).not.toContain('sw-native-block-host');
    });
});
