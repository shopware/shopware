/**
 * @sw-package framework
 *
 * End-to-end: the component factory injects the composition-override setup into an Options API base,
 * so a native-setup override actually replaces the base's state.
 */

import { h, ref } from 'vue';
import { mount } from '@vue/test-utils';
import ComponentFactory from 'src/core/factory/async-component.factory';
import { overrideComponentSetup, _overridesMap } from 'src/app/adapter/composition-extension-system';
import { createOptionsBaseOverrideSetup } from 'src/app/adapter/options-base-override';

describe('src/core/factory: composition override applied to an Options API base', () => {
    beforeAll(() => {
        // shopware.ts wires this onto Shopware.Component in the running app; do the same for the test so
        // the factory's build() can resolve it through the global.
        (Shopware.Component as unknown as Record<string, unknown>).createOptionsBaseOverrideSetup =
            createOptionsBaseOverrideSetup;
    });

    beforeEach(() => {
        ComponentFactory.getComponentRegistry().clear();
        ComponentFactory.getOverrideRegistry().clear();
        Object.keys(_overridesMap).forEach((key) => delete _overridesMap[key]);
        jest.restoreAllMocks();
    });

    it('replaces an Options base data field via a composition override, end to end', async () => {
        ComponentFactory.register('sw-factory-opt', {
            data: () => ({ headline: 'Base' }),
            render() {
                return h('div', (this as unknown as { headline: string }).headline);
            },
        });

        overrideComponentSetup()('sw-factory-opt', () => ({ headline: ref('Overridden') }));

        const config = await ComponentFactory.build('sw-factory-opt');
        expect(typeof config).not.toBe('boolean');

        const wrapper = mount(config as Parameters<typeof mount>[0]);
        expect(wrapper.text()).toBe('Overridden');
    });

    it('leaves an Options component without an override completely untouched (no injected setup)', async () => {
        ComponentFactory.register('sw-factory-plain', {
            data: () => ({ headline: 'Base' }),
            render() {
                return h('div', (this as unknown as { headline: string }).headline);
            },
        });

        const config = await ComponentFactory.build('sw-factory-plain');
        expect(typeof config).not.toBe('boolean');

        // No override targets it -> the factory injects nothing.
        expect(typeof (config as { setup?: unknown }).setup).toBe('undefined');
        expect(mount(config as Parameters<typeof mount>[0]).text()).toBe('Base');
    });
});
