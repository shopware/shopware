/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import type { ComponentConfig } from 'src/core/factory/async-component.factory';
import { attachSetupOverrideShim } from '../options-api-setup-shim';
import { _overridesMap } from '../index';

describe('src/app/adapter/composition-extension-system/options-api-setup-shim - inheritance', () => {
    beforeEach(() => {
        Object.keys(_overridesMap).forEach((key) => {
            delete _overridesMap[key];
        });
    });

    it('applies method overrides before a created() hook inherited through extends', async () => {
        const calls: string[] = [];

        _overridesMap['sw-shim-extends'] = [
            () => ({
                createdComponent: () => {
                    calls.push('override');
                },
            }),
        ] as never;

        // Same shape Component.build() produces for a legacy Component.override() or Component.extend()
        const config = {
            template: '<p />',
            extends: {
                created(this: { createdComponent: () => void }) {
                    this.createdComponent();
                },
                methods: {
                    createdComponent() {
                        calls.push('base');
                    },
                },
            },
        } as unknown as ComponentConfig;

        attachSetupOverrideShim('sw-shim-extends', config);

        mount(config as never);
        await flushPromises();

        expect(calls).toEqual(['override']);
    });

    it('runs before every created() hook of a nested extends chain and its mixins', async () => {
        const calls: string[] = [];

        _overridesMap['sw-shim-extends-chain'] = [
            () => ({
                createdComponent: () => {
                    calls.push('override');
                },
            }),
        ] as never;

        // A legacy Component.override() of a component that was created with Component.extend(): the
        // override wraps the extending component, which wraps its base.
        const config = {
            template: '<p />',
            extends: {
                extends: {
                    mixins: [
                        {
                            created(this: { createdComponent: () => void }) {
                                this.createdComponent();
                            },
                        },
                    ],
                    methods: {
                        createdComponent() {
                            calls.push('base');
                        },
                    },
                },
            },
        } as unknown as ComponentConfig;

        attachSetupOverrideShim('sw-shim-extends-chain', config);

        mount(config as never);
        await flushPromises();

        expect(calls).toEqual(['override']);
    });
});
