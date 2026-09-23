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

        _overridesMap.clear();
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
    const { legacyBlockHelpers } = await import(
        'src/app/component/structure/sw-block-override/shim/legacy-condition-context'
    );

    return mount(await ComponentFactory.build(componentName), {
        global: {
            components: {
                'sw-block': swBlock,
                'sw-block-parent': swBlockParent,
            },
            plugins: [createDataScopeFixture()],
            config: {
                globalProperties: legacyBlockHelpers,
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
 * Waits for the re-render of blocks that read condition results written by another block.
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
