/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import ComponentFactory from 'src/core/factory/async-component.factory';
import TemplateFactory from 'src/core/factory/template.factory';
import * as twigBlockIndex from 'src/core/factory/twig-block-index';
import { _overridesMap } from 'src/app/adapter/composition-extension-system';
import getBlockDataScope from 'src/app/component/structure/sw-block-override/sw-block/get-block-data-scope';

export { ComponentFactory, mount };

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

export async function mountNativeBlockComponent(componentName) {
    const swBlock = (await import('src/app/component/structure/sw-block-override/sw-block/index')).default;
    const swBlockParent = (await import('src/app/component/structure/sw-block-override/sw-block-parent/index')).default;
    const useLegacyConditionContext = (
        await import('src/app/component/structure/sw-block-override/shim/legacy-condition-context')
    ).default;
    const { legacyIf, legacyElseIf, legacyElse } = useLegacyConditionContext();

    const getLegacyBlockConditionKey = (vm, blockName) => {
        const componentUid = vm.$?.uid;

        if (typeof componentUid !== 'number') {
            return blockName;
        }

        return `${componentUid}:${blockName}`;
    };

    return mount(await ComponentFactory.build(componentName), {
        global: {
            components: {
                'sw-block': swBlock,
                'sw-block-parent': swBlockParent,
            },
            mocks: {
                $dataScope: getBlockDataScope,
            },
            config: {
                globalProperties: {
                    $swLegacyBlockIf(blockName, expression, options) {
                        return legacyIf(getLegacyBlockConditionKey(this, blockName), expression, options);
                    },
                    $swLegacyBlockElseIf(blockName, expression, options) {
                        return legacyElseIf(getLegacyBlockConditionKey(this, blockName), expression, options);
                    },
                    $swLegacyBlockElse(blockName, options) {
                        return legacyElse(getLegacyBlockConditionKey(this, blockName), options);
                    },
                },
            },
        },
    });
}

export function expectOnlyBranch(wrapper, branches, visibleBranch) {
    branches.forEach((branch) => {
        expect(wrapper.find(branch).exists(), branch).toBe(branch === visibleBranch);
    });
}

export async function settleLegacyChain(wrapper) {
    await wrapper.vm.$nextTick();
    await wrapper.vm.$nextTick();
}

export async function withMutedConsoleWarn(callback) {
    const consoleWarn = jest.spyOn(console, 'warn').mockImplementation(() => {});

    try {
        return await callback();
    } finally {
        consoleWarn.mockRestore();
    }
}
