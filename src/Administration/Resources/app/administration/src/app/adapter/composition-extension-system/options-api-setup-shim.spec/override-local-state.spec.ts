/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import { computed, ref } from 'vue';
import type { ComponentConfig } from 'src/core/factory/async-component.factory';
import swBlock from 'src/app/component/structure/sw-block-override/sw-block/index';
import { attachSetupOverrideShim } from '../options-api-setup-shim';
import { _overridesMap } from '../index';

// What the shim hands to an override: every base-state key served as a ref-like accessor.
type PreviousState = Record<string, { value: unknown }>;

describe('src/app/adapter/composition-extension-system/options-api-setup-shim - override-local state', () => {
    beforeEach(() => {
        Object.keys(_overridesMap).forEach((key) => {
            delete _overridesMap[key];
        });
    });

    it('serves override-local state unwrapped through the data scope of a block', async () => {
        // What the Vite override transform emits for a binding only the override template uses: filed
        // under `__swOverride` by file namespace, and destructured from the `<sw-block>` slot scope.
        const namespace = Symbol('sw-shim-local.override');

        _overridesMap['sw-shim-local'] = [
            (previousState: PreviousState) => ({
                __swOverride: {
                    [namespace]: { margin: computed(() => Number(previousState.price.value) / 10) },
                },
            }),
        ] as never;

        const config = {
            components: {
                'sw-block': swBlock,
                'margin-hint': {
                    props: { margin: { type: Number, required: true } },
                    template: '<span>{{ margin + 1 }}</span>',
                },
            },
            template: `
                <sw-block name="sw-shim-local-block" :data="$dataScope">
                    <template #default="{ __swOverride }">
                        <margin-hint :margin="__swOverride[namespace].margin" />
                    </template>
                </sw-block>`,
            data() {
                return { price: 100, namespace };
            },
        } as unknown as ComponentConfig;

        attachSetupOverrideShim('sw-shim-local', config);

        const wrapper = mount(config as never);
        await flushPromises();

        // setupState only unwraps top-level refs. Without the reactive container the child would
        // receive the ComputedRef itself and render "NaN"; interpolation would hide that because
        // toDisplayString() unwraps.
        expect(wrapper.text()).toBe('11');
    });

    it('merges the override-local state of several override files and hides it from previousState', async () => {
        const first = Symbol('first.override');
        const second = Symbol('second.override');
        let seenByLaterOverride: unknown = 'not read';

        _overridesMap['sw-shim-local-merge'] = [
            () => ({ __swOverride: { [first]: { a: ref(1) } } }),
            (previousState: PreviousState) => {
                seenByLaterOverride = previousState.__swOverride;

                return { __swOverride: { [second]: { b: ref(2) } } };
            },
        ] as never;

        const config = {
            template: '<p />',
        } as unknown as ComponentConfig;

        attachSetupOverrideShim('sw-shim-local-merge', config);

        const wrapper = mount(config as never);
        await flushPromises();

        // `$dataScope` falls back to the instance proxy for Twig components, so this is what the block
        // slot scope reads. Assigning instead of merging would drop the first file's namespace and make
        // its generated slot scope destructure from undefined.
        const localState = (wrapper.vm as unknown as Record<string, Record<symbol, Record<string, unknown>>>).__swOverride;
        expect(localState[first].a).toBe(1);
        expect(localState[second].b).toBe(2);

        // Override-local state is template plumbing, not base state; migrated components hide it too.
        expect(seenByLaterOverride).toBeUndefined();
    });
});
