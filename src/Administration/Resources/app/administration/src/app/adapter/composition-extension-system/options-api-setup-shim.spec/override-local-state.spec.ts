/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import { computed, ref } from 'vue';
import type { ComponentConfig } from 'src/core/factory/async-component.factory';
import swBlock from 'src/app/component/structure/sw-block-override/sw-block/index';
import { attachSetupOverrideShim } from '../options-api-setup-shim';
import { _overridesMap, overrideComponentSetup } from '../index';

// What the shim hands to an override: every base-state key served as a ref-like accessor.
type PreviousState = Record<string, { value: unknown }>;

/**
 * What the Vite transform makes of an override SFC: the hidden component exposes the bindings
 * `overrideComponentSetup()` returned, and its `<sw-block extends>` content reads them.
 */
function createOverrideComponent(bindings: Record<string, unknown>, blockContent: string) {
    return {
        components: {
            'sw-block': swBlock,
            'margin-hint': {
                props: { margin: { type: Number, required: true } },
                template: '<span class="margin">{{ margin + 1 }}</span>',
            },
        },
        setup: () => bindings,
        template: `
            <sw-block extends="sw-shim-local-block" sw-internal-component-name="sw-shim-local">
                <sw-block-parent />
                ${blockContent}
            </sw-block>`,
    };
}

function createBaseConfig(): ComponentConfig {
    const config = {
        components: { 'sw-block': swBlock },
        props: { price: { type: Number, required: true } },
        template: `
            <sw-block name="sw-shim-local-block" sw-internal-component-name="sw-shim-local" :data="$dataScope">
                <span class="price">{{ price }}</span>
            </sw-block>`,
    } as unknown as ComponentConfig;

    attachSetupOverrideShim('sw-shim-local', config);

    return config;
}

describe('src/app/adapter/composition-extension-system/options-api-setup-shim - override-local state', () => {
    beforeEach(() => {
        Object.keys(_overridesMap).forEach((key) => {
            delete _overridesMap[key];
        });
    });

    it('serves each Twig instance its own override-local value inside its block', async () => {
        const { margin } = overrideComponentSetup()('sw-shim-local', (previousState) => ({
            override: {},
            local: { margin: computed(() => Number((previousState as PreviousState).price.value) / 10) },
        }));

        const hidden = mount(createOverrideComponent({ margin }, '<margin-hint :margin="margin" />'));
        const config = createBaseConfig();
        const first = mount(config as never, { props: { price: 100 } as never });
        const second = mount(config as never, { props: { price: 200 } as never });
        await flushPromises();

        // Props receive the unwrapped value: the binding acts as the computed while the block renders.
        expect(first.find('.margin').text()).toBe('11');
        expect(second.find('.margin').text()).toBe('21');

        hidden.unmount();
    });

    it('keeps the locals of two override files apart even when they share a name', async () => {
        const firstFile = overrideComponentSetup()('sw-shim-local', () => ({
            override: {},
            local: { label: ref('first file') },
        }));
        const secondFile = overrideComponentSetup()('sw-shim-local', () => ({
            override: {},
            local: { label: ref('second file') },
        }));

        const hiddenFirst = mount(
            createOverrideComponent({ label: firstFile.label }, '<span class="label">{{ label }}</span>'),
        );
        const hiddenSecond = mount(
            createOverrideComponent({ label: secondFile.label }, '<span class="label">{{ label }}</span>'),
        );
        const wrapper = mount(createBaseConfig() as never, { props: { price: 100 } as never });
        await flushPromises();

        expect(wrapper.findAll('.label').map((label) => label.text())).toEqual(['first file', 'second file']);

        hiddenFirst.unmount();
        hiddenSecond.unmount();
    });
});
