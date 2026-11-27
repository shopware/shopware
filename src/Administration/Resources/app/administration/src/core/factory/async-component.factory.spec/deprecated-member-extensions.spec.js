/**
 * @sw-package framework
 */

import { deprecations, useProductionDeprecationGuard } from 'test/_helper_/deprecations';
import { ComponentFactory, setupComponentFactoryHooks } from './native-block-condition.fixtures';
import { registerComponentWithDeprecatedMembers } from './deprecated-members.fixtures';

describe('core/factory/async-component.factory.ts - extensions that redefine deprecated members', () => {
    setupComponentFactoryHooks();

    it('reports a redefinition of a deprecated member of the parent, with the message of its guard', async () => {
        registerComponentWithDeprecatedMembers('sw-extended-parent');
        ComponentFactory.extend('sw-redefining-child', 'sw-extended-parent', {
            computed: {
                oldFilter() {
                    return 'redefined';
                },
            },
            methods: {
                oldMethod() {
                    return 'redefined';
                },
            },
        });

        await ComponentFactory.build('sw-redefining-child');

        expect(deprecations).toHaveTriggered(
            "Component.extend('sw-redefining-child', 'sw-extended-parent') redefines the deprecated computed \"oldFilter\", which is removed with V6_8_0_0. Rename it or deprecate it as well.",
        );
        expect(deprecations).toHaveTriggered(
            'redefines the deprecated method "oldMethod", which is removed with V6_8_0_0. Rename it or deprecate it as well. The method is deprecated with: oldMethod is deprecated, use newMethod.',
        );
    });

    it('does not report a redefinition that is deprecated itself, or the members the component only inherits', async () => {
        registerComponentWithDeprecatedMembers('sw-guarded-parent');
        ComponentFactory.extend('sw-guarded-child', 'sw-guarded-parent', {
            computed: {
                filter() {
                    return 'redefined';
                },
            },
            methods: {
                /**
                 * @deprecated tag:v6.8.0 - Will be removed, use `newMethod` instead.
                 */
                oldMethod() {
                    Shopware.Feature.triggerDeprecationOrThrow('V6_8_0_0', 'sw-guarded-child.oldMethod is deprecated.');

                    return 'redefined';
                },
            },
        });

        await ComponentFactory.build('sw-guarded-child');
        await ComponentFactory.build('sw-guarded-child');

        expect(deprecations.entries).toHaveLength(0);
    });

    describe('registered inside Feature.silence()', () => {
        const originalFlags = { ...Shopware.Feature.flags };

        beforeEach(() => {
            Shopware.Feature.init({ V7_0_0_0: false });
        });

        afterEach(() => {
            Shopware.Feature.flags = { ...originalFlags };
        });

        it('does not report a redefinition silenced for the major of the member', async () => {
            ComponentFactory.register('sw-silenced-parent', {
                template: '<div></div>',
                methods: {
                    oldMethod() {
                        Shopware.Feature.triggerDeprecationOrThrow('V7_0_0_0', 'oldMethod is deprecated.');

                        return 'oldMethod';
                    },
                },
            });
            Shopware.Feature.silence('V7_0_0_0', () => {
                ComponentFactory.extend('sw-silenced-child', 'sw-silenced-parent', {
                    methods: {
                        oldMethod() {
                            return 'redefined';
                        },
                    },
                });
            });

            await ComponentFactory.build('sw-silenced-child');

            expect(deprecations.entries).toHaveLength(0);
        });
    });

    it.activeFeatureFlags(['v6.8.0.0'])(
        'logs an error for a redefinition instead of throwing once the major flag is active',
        async () => {
            useProductionDeprecationGuard();
            const error = jest.spyOn(console, 'error').mockImplementation(() => {});
            registerComponentWithDeprecatedMembers('sw-active-major-parent');
            ComponentFactory.extend('sw-active-major-child', 'sw-active-major-parent', {
                methods: {
                    oldMethod() {
                        return 'redefined';
                    },
                },
            });

            await expect(ComponentFactory.build('sw-active-major-child')).resolves.toBeTruthy();
            expect(error).toHaveBeenCalledWith(
                '[Deprecation]',
                expect.stringContaining(
                    "Component.extend('sw-active-major-child', 'sw-active-major-parent') redefines the deprecated method \"oldMethod\"",
                ),
            );
        },
    );
});
