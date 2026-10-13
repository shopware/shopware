/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import type { ComponentConfig } from 'src/core/factory/async-component.factory';
import { attachSetupOverrideShim } from '../options-api-setup-shim';
import { _overridesMap } from '../index';

describe('src/app/adapter/composition-extension-system/options-api-setup-shim - registration timing', () => {
    beforeEach(() => {
        Object.keys(_overridesMap).forEach((key) => {
            delete _overridesMap[key];
        });
    });

    it('applies an override registered after the shim was attached', async () => {
        const config = {
            template: '<span>{{ label }}</span>',
            data() {
                return { label: 'base' };
            },
        } as unknown as ComponentConfig;

        // Boot order for sync components: built (shim attached) before the override SFCs mount
        // and register.
        attachSetupOverrideShim('sw-shim-late', config);
        _overridesMap['sw-shim-late'] = [() => ({ label: 'late override' })] as never;

        const wrapper = mount(config as never);
        await flushPromises();

        expect(wrapper.text()).toBe('late override');
    });

    it('leaves the setup result of an untargeted component untouched', () => {
        const originalResult = { label: 'base' };
        const config = {
            template: '<span>{{ label }}</span>',
            setup: () => originalResult,
        } as unknown as ComponentConfig;

        attachSetupOverrideShim('sw-shim-untargeted', config);

        const wrapper = mount(config as never);

        expect(wrapper.text()).toBe('base');
        // No override-local container was exposed on it.
        expect(Object.getOwnPropertyNames(originalResult)).toEqual(['label']);
    });
});
