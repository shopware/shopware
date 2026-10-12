/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import ComponentFactory from 'src/core/factory/async-component.factory';
import TemplateFactory from 'src/core/factory/template.factory';
import * as twigBlockIndex from 'src/core/factory/twig-block-index';
import { _overridesMap } from 'src/app/adapter/composition-extension-system';
import createDataScopeFixture from 'src/app/component/structure/sw-block-override/sw-block-override.spec/test-utils/create-data-scope-fixture';

export { ComponentFactory, mount };

/**
 * Registers the shared Jest reset hooks for native-block condition-chain specs.
 * Use it at the top of spec files that build components through `ComponentFactory`.
 *
 * @example
 * setupComponentFactoryHooks();
 */
export function setupComponentFactoryHooks() {
    beforeEach(async () => {
        ComponentFactory.getComponentRegistry().clear();
        ComponentFactory.getOverrideRegistry().clear();
        ComponentFactory._clearComponentHelper();
        TemplateFactory.getTemplateRegistry().clear();
        TemplateFactory.getNormalizedTemplateRegistry().clear();
        TemplateFactory.disableTwigCache();
        ComponentFactory.markComponentTemplatesAsNotResolved();

        const entries = [...Object.keys(_overridesMap)];
        entries.forEach((key) => {
            delete _overridesMap[key];
        });
        twigBlockIndex.resetBlockIndex();
    });
}

/**
 * Builds and mounts a component with the native block components and legacy condition helpers installed.
 * Use it in integration-style specs that need the same runtime wiring as the administration app.
 *
 * @example
 * const wrapper = await mountNativeBlockComponent('sw-test-component');
 */
export async function mountNativeBlockComponent(componentName) {
    const swBlock = (await import('src/app/component/structure/sw-block-override/sw-block/index')).default;
    const swBlockParent = (await import('src/app/component/structure/sw-block-override/sw-block-parent/index')).default;
    const useLegacyConditionContext = (
        await import('src/app/component/structure/sw-block-override/shim/legacy-condition-context')
    ).default;
    const { legacyIf, legacyElseIf, legacyElse } = useLegacyConditionContext();

    /**
     * Scopes a test helper condition chain to the mounted component instance.
     * Use it before forwarding mocked `$swLegacyBlock*` calls to the shared legacy condition runtime.
     *
     * @example
     * getLegacyBlockConditionKey(wrapper.vm, 'sw_card:0');
     */
    const getLegacyBlockConditionKey = (vm, blockName) => {
        const componentUid = vm.$?.uid;

        if (typeof componentUid !== 'number') {
            return blockName;
        }

        return `${componentUid}:${blockName}`;
    };
    const globalProperties = {
        /**
         * Starts a transformed condition chain in mounted test components.
         * Use it when generated templates in these specs call `$swLegacyBlockIf`.
         *
         * @example
         * this.$swLegacyBlockIf('sw_card:0', true, options);
         */
        $swLegacyBlockIf(blockName, expression, options) {
            return legacyIf(getLegacyBlockConditionKey(this, blockName), expression, options);
        },
        /**
         * Continues a transformed condition chain in mounted test components.
         * Use it when generated templates in these specs call `$swLegacyBlockElseIf`.
         *
         * @example
         * this.$swLegacyBlockElseIf('sw_card:0', false, options);
         */
        $swLegacyBlockElseIf(blockName, expression, options) {
            return legacyElseIf(getLegacyBlockConditionKey(this, blockName), expression, options);
        },
        /**
         * Finishes a transformed condition chain in mounted test components.
         * Use it when generated templates in these specs call `$swLegacyBlockElse`.
         *
         * @example
         * this.$swLegacyBlockElse('sw_card:0', options);
         */
        $swLegacyBlockElse(blockName, options) {
            return legacyElse(getLegacyBlockConditionKey(this, blockName), options);
        },
    };

    return mount(await ComponentFactory.build(componentName), {
        global: {
            components: {
                'sw-block': swBlock,
                'sw-block-parent': swBlockParent,
            },
            plugins: [createDataScopeFixture()],
            config: {
                globalProperties,
            },
        },
    });
}

/**
 * Asserts that exactly one selector from a branch set is visible.
 * Use it after toggling state in condition-chain specs.
 *
 * @example
 * expectOnlyBranch(wrapper, ['.branch-a', '.branch-b'], '.branch-a');
 */
export function expectOnlyBranch(wrapper, branches, visibleBranch) {
    branches.forEach((branch) => {
        expect(wrapper.find(branch).exists(), branch).toBe(branch === visibleBranch);
    });
}

/**
 * Waits for the extra ticks needed by legacy condition reservations and re-renders.
 * Use it after changing state that affects a transformed condition chain.
 *
 * @example
 * await settleLegacyChain(wrapper);
 */
export async function settleLegacyChain(wrapper) {
    await wrapper.vm.$nextTick();
    await wrapper.vm.$nextTick();
}

/**
 * Runs a callback while suppressing deprecation warnings expected from legacy Twig shims.
 * Use it around specs that intentionally mount legacy override content.
 *
 * @example
 * await withMutedConsoleWarn(async () => mountNativeBlockComponent('sw-test-component'));
 */
export async function withMutedConsoleWarn(callback) {
    const consoleWarn = jest.spyOn(console, 'warn').mockImplementation(() => {});

    try {
        return await callback();
    } finally {
        consoleWarn.mockRestore();
    }
}
