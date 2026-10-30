/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { h } from 'vue';
import type { ComponentOptions } from 'vue';
import type { ComponentConfig, SetupHmrMetadata } from 'src/core/factory/async-component.factory';
import { trackNativeHmrDefinition } from './script-hmr';

describe('native definitions updated before their first mount', () => {
    let wrapper: VueWrapper | undefined;
    afterEach(() => {
        wrapper?.unmount();
        wrapper = undefined;
    });

    it.each([
        { originalName: undefined, updatedName: undefined, expectedName: undefined },
        { originalName: 'sw-native-cached', updatedName: undefined, expectedName: 'sw-native-cached' },
        { originalName: 'sw-native-cached', updatedName: 'generated-name', expectedName: 'sw-native-cached' },
        { originalName: undefined, updatedName: 'generated-name', expectedName: 'generated-name' },
    ])(
        'uses current props and data (original name: $originalName, updated name: $updatedName)',
        ({ originalName, updatedName, expectedName }) => {
            const data: NonNullable<SetupHmrMetadata['data']> = {};
            const definition = {
                ...(originalName ? { name: originalName } : {}),
                data: () => ({ value: 'Stale data' }),
                render: () => h('p', 'Before'),
                __shopwareSetupHmr: {
                    componentName: 'sw-native-first-mount',
                    mode: 'base',
                    version: '1',
                    invalidate: jest.fn(),
                    data,
                },
            } as ComponentConfig;
            trackNativeHmrDefinition(definition);
            const update = {
                ...(updatedName ? { name: updatedName } : {}),
                props: ['value'],
                render(this: { value: string }) {
                    return h('p', this.value);
                },
            } as ComponentConfig;
            data.updateDefinitions?.(update);
            wrapper = mount(definition as ComponentOptions, { props: { value: 'Updated prop' } });
            expect(wrapper.text()).toBe('Updated prop');
            expect(definition.name).toBe(expectedName);
        },
    );
});
