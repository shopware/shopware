/**
 * @sw-package framework
 */

import type { ComponentConfig } from 'src/core/factory/async-component.factory';
import { convertOptionsApiOverrideToCompositionApi, shouldActivateShim } from 'src/app/adapter/options-composition-shim';

describe('src/app/adapter/options-composition-shim: activation and warnings', () => {
    describe('shouldActivateShim()', () => {
        it.each([
            ['methods', { methods: { save() {} } }],
            ['computed', { computed: { label: () => '' } }],
            ['data', { data: () => ({}) }],
            ['watch', { watch: { count() {} } }],
            ['inject', { inject: ['repositoryFactory'] }],
            ['mixins', { mixins: [{ methods: {} }] }],
            ['a lifecycle hook', { created() {} }],
            ['extends', { extends: 'sw-base' }],
        ])('activates for %s', (_label, config) => {
            expect(shouldActivateShim(config as ComponentConfig)).toBe(true);
        });

        it.each([
            ['an empty config', {}],
            ['a template-only config', { template: '<div />' }],
            ['an empty mixins array', { mixins: [] }],
        ])('does not activate for %s', (_label, config) => {
            expect(shouldActivateShim(config as ComponentConfig)).toBe(false);
        });
    });

    it('logs a deprecation warning with the migration docs link', () => {
        const warn = jest.spyOn(console, 'warn').mockImplementation(() => {});

        convertOptionsApiOverrideToCompositionApi('sw-shim-warnings', { methods: {} } as ComponentConfig);

        expect(warn).toHaveBeenCalledWith(
            expect.stringMatching(
                /^\[Deprecation Warning\] Component "sw-shim-warnings" is being overridden with Options API patterns.*https:\/\/developer\.shopware\.com\/docs\/resources\/references\/core-reference\/administration-reference\/composition-api$/,
            ),
        );
        warn.mockRestore();
    });

    it('logs an error for a render function', () => {
        const warn = jest.spyOn(console, 'warn').mockImplementation(() => {});
        const error = jest.spyOn(console, 'error').mockImplementation(() => {});

        convertOptionsApiOverrideToCompositionApi('sw-shim-warnings', { render: () => null } as ComponentConfig);

        expect(error).toHaveBeenCalledWith(expect.stringContaining('Custom render() functions are not supported'));
        warn.mockRestore();
        error.mockRestore();
    });

    it('warns for every unsupported option', () => {
        const warn = jest.spyOn(console, 'warn').mockImplementation(() => {});

        convertOptionsApiOverrideToCompositionApi('sw-shim-warnings', {
            components: {},
            directives: {},
            provide: {},
            template: '<div />',
            extends: 'sw-base',
            inheritAttrs: false,
            emits: ['save'],
        } as unknown as ComponentConfig);

        [
            'components',
            'directives',
            'provide',
            'template',
            'extends',
            'emits',
        ].forEach((option) => {
            expect(warn).toHaveBeenCalledWith(
                `[Options API Shim] "${option}" is not supported by the compatibility shim in component "sw-shim-warnings". This option will be ignored.`,
            );
        });
        warn.mockRestore();
    });
});
