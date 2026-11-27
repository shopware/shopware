/**
 * @sw-package framework
 */

import deprecationBaseline from 'src/core/deprecation-baseline.json';
import swUploadListener from 'src/app/component/utils/sw-upload-listener';
import { deprecations, useProductionDeprecationGuard } from 'test/_helper_/deprecations';
import { ComponentFactory, mount, setupComponentFactoryHooks } from './native-block-condition.fixtures';
import { registerComponentWithDeprecatedMembers } from './deprecated-members.fixtures';

describe('core/factory/async-component.factory.ts - overrides of deprecated members', () => {
    setupComponentFactoryHooks();

    const listedBaselineKeys = [];

    afterEach(() => {
        listedBaselineKeys.splice(0).forEach((key) => {
            delete deprecationBaseline[key];
        });
    });

    it('reports an override of a deprecated computed without $super', async () => {
        registerComponentWithDeprecatedMembers('sw-replaced-computed');
        ComponentFactory.override('sw-replaced-computed', {
            computed: {
                oldFilter() {
                    return 'overridden';
                },
            },
        });

        await ComponentFactory.build('sw-replaced-computed');

        expect(deprecations).toHaveTriggered(
            'Component.override(\'sw-replaced-computed\') overrides the deprecated computed "oldFilter", so the override stops working once "oldFilter" is removed with V6_8_0_0.',
        );
    });

    it('reports an override of a deprecated method without $super, with the message of its guard', async () => {
        registerComponentWithDeprecatedMembers('sw-replaced-method');
        ComponentFactory.override('sw-replaced-method', {
            methods: {
                oldMethod() {
                    return 'overridden';
                },
            },
        });

        await ComponentFactory.build('sw-replaced-method');

        expect(deprecations).toHaveTriggered(
            'overrides the deprecated method "oldMethod", so the override stops working once "oldMethod" is removed with V6_8_0_0. The method is deprecated with: oldMethod is deprecated, use newMethod.',
        );
    });

    it('leaves out a guard message that is no plain string literal', async () => {
        registerComponentWithDeprecatedMembers('sw-template-message');
        ComponentFactory.override('sw-template-message', {
            computed: {
                oldFilter() {
                    return 'overridden';
                },
            },
        });

        await ComponentFactory.build('sw-template-message');

        expect(deprecations).toHaveTriggered(/removed with V6_8_0_0\.$/);
    });

    it('reports an override that calls $super at build, and the guard of the member once it runs', async () => {
        registerComponentWithDeprecatedMembers('sw-super-computed');
        ComponentFactory.override('sw-super-computed', {
            computed: {
                oldFilter() {
                    return `${this.$super('oldFilter')}!`;
                },
            },
        });

        const wrapper = mount(await ComponentFactory.build('sw-super-computed'));

        expect(wrapper.text()).toBe('oldFilter!');
        expect(deprecations).toHaveTriggered(
            'Component.override(\'sw-super-computed\') overrides the deprecated computed "oldFilter", so the override stops working once "oldFilter" is removed with V6_8_0_0.',
        );
        expect(deprecations).toHaveTriggered('sw-super-computed.oldFilter is deprecated, use filter instead.');
    });

    it('does not report an override of a member that is not deprecated', async () => {
        registerComponentWithDeprecatedMembers('sw-not-deprecated');
        ComponentFactory.override('sw-not-deprecated', {
            computed: {
                filter() {
                    return 'overridden';
                },
            },
            methods: {
                newMethod() {
                    return 'new';
                },
            },
        });

        await ComponentFactory.build('sw-not-deprecated');

        expect(deprecations.entries).toHaveLength(0);
    });

    it.each([
        [
            'getter and setter',
            {
                get() {
                    return 'overridden';
                },
                set(value) {
                    this._value = value;
                },
            },
        ],
        [
            'getter only',
            {
                get() {
                    return 'overridden';
                },
            },
        ],
        [
            'setter only',
            {
                get() {
                    return this.$super('oldValue.get');
                },
                set(value) {
                    this._value = value;
                },
            },
        ],
        [
            'function',
            function oldValue() {
                return 'overridden';
            },
        ],
    ])('reports an override of the %s of a deprecated getter/setter computed', async (_, oldValue) => {
        registerComponentWithDeprecatedMembers('sw-replaced-accessor');
        ComponentFactory.override('sw-replaced-accessor', {
            computed: { oldValue },
        });

        await ComponentFactory.build('sw-replaced-accessor');

        expect(deprecations).toHaveTriggered(
            'overrides the deprecated computed "oldValue", so the override stops working once "oldValue" is removed with V6_8_0_0. The computed is deprecated with: oldValue is deprecated.',
        );
    });

    it('reports an override that calls $super in the getter and the setter', async () => {
        registerComponentWithDeprecatedMembers('sw-super-accessor');
        ComponentFactory.override('sw-super-accessor', {
            computed: {
                oldValue: {
                    get() {
                        return this.$super('oldValue.get');
                    },
                    set(value) {
                        this.$super('oldValue.set', value);
                    },
                },
            },
        });

        await ComponentFactory.build('sw-super-accessor');

        expect(deprecations).toHaveTriggered(
            'Component.override(\'sw-super-accessor\') overrides the deprecated computed "oldValue", so the override stops working once "oldValue" is removed with V6_8_0_0.',
        );
    });

    it('reports an override of a deprecated method of a component from src, which CI instruments for coverage', async () => {
        ComponentFactory.register('sw-upload-listener', swUploadListener);
        ComponentFactory.override('sw-upload-listener', {
            methods: {
                updateSuccessNotification() {
                    return null;
                },
            },
        });

        await ComponentFactory.build('sw-upload-listener');

        expect(deprecations).toHaveTriggered(
            'Component.override(\'sw-upload-listener\') overrides the deprecated method "updateSuccessNotification"',
        );
    });

    it('reports an override of a deprecated member its component inherits', async () => {
        registerComponentWithDeprecatedMembers('sw-deprecated-parent');
        ComponentFactory.extend('sw-child', 'sw-deprecated-parent', {
            methods: {
                oldMethod() {
                    return `child ${this.$super('oldMethod')}`;
                },
            },
        });
        ComponentFactory.override('sw-child', {
            methods: {
                oldMethod() {
                    return 'overridden';
                },
            },
        });

        await ComponentFactory.build('sw-child');

        expect(deprecations).toHaveTriggered('Component.override(\'sw-child\') overrides the deprecated method "oldMethod"');
        expect(deprecations).toHaveTriggered(
            "Component.extend('sw-child', 'sw-deprecated-parent') redefines the deprecated method \"oldMethod\"",
        );
    });

    it('does not take a guard behind a condition for the deprecation of the member', async () => {
        ComponentFactory.register('sw-conditional-guard', {
            template: '<div></div>',
            methods: {
                method: new Function(
                    'legacy',
                    'if (legacy) {\n    Shopware.Feature.triggerDeprecationOrThrow("V6_8_0_0", "legacy is deprecated.");\n}\nreturn 1;',
                ),
            },
        });
        ComponentFactory.override('sw-conditional-guard', {
            methods: {
                method() {
                    return 'overridden';
                },
            },
        });

        await ComponentFactory.build('sw-conditional-guard');

        expect(deprecations.entries).toHaveLength(0);
    });

    it('does not report in production', async () => {
        const nodeEnv = process.env.NODE_ENV;
        process.env.NODE_ENV = 'production';

        try {
            registerComponentWithDeprecatedMembers('sw-production');
            ComponentFactory.override('sw-production', {
                methods: {
                    oldMethod() {
                        return 'overridden';
                    },
                },
            });

            await ComponentFactory.build('sw-production');
        } finally {
            process.env.NODE_ENV = nodeEnv;
        }

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

        /**
         * Registers `componentName` with the method `oldMethod`, deprecated for `V7_0_0_0`, a major neither test
         * lane activates, and overrides it inside `Feature.silence(silencedMajor)`.
         */
        function overrideInsideSilence(componentName, silencedMajor) {
            ComponentFactory.register(componentName, {
                template: '<div></div>',
                methods: {
                    oldMethod() {
                        Shopware.Feature.triggerDeprecationOrThrow('V7_0_0_0', 'oldMethod is deprecated.');

                        return 'oldMethod';
                    },
                },
            });
            Shopware.Feature.silence(silencedMajor, () => {
                ComponentFactory.override(componentName, {
                    methods: {
                        oldMethod() {
                            return 'overridden';
                        },
                    },
                });
            });
        }

        it('does not report an override silenced for the major of the member', async () => {
            overrideInsideSilence('sw-silenced-override', 'V7_0_0_0');

            await ComponentFactory.build('sw-silenced-override');

            expect(deprecations.entries).toHaveLength(0);
        });

        it('reports an override silenced for another major', async () => {
            overrideInsideSilence('sw-other-major-silenced', 'V6_9_0_0');

            await ComponentFactory.build('sw-other-major-silenced');

            expect(deprecations).toHaveTriggered(
                'Component.override(\'sw-other-major-silenced\') overrides the deprecated method "oldMethod"',
            );
        });

        it.activeFeatureFlags(['v7.0.0.0'])(
            'logs an error for a silenced override once the major flag is active',
            async () => {
                useProductionDeprecationGuard();
                const error = jest.spyOn(console, 'error').mockImplementation(() => {});
                overrideInsideSilence('sw-silenced-active-major', 'V7_0_0_0');

                await expect(ComponentFactory.build('sw-silenced-active-major')).resolves.toBeTruthy();
                expect(error).toHaveBeenCalledWith(
                    '[Deprecation]',
                    expect.stringContaining(
                        'Tried to access deprecated functionality: Component.override(\'sw-silenced-active-major\') overrides the deprecated method "oldMethod"',
                    ),
                );
            },
        );
    });

    it.activeFeatureFlags(['v6.8.0.0'])(
        'logs an error for every overridden member instead of throwing once the major flag is active',
        async () => {
            useProductionDeprecationGuard();
            const error = jest.spyOn(console, 'error').mockImplementation(() => {});
            registerComponentWithDeprecatedMembers('sw-active-major');
            ComponentFactory.override('sw-active-major', {
                computed: {
                    oldFilter() {
                        return 'overridden';
                    },
                },
                methods: {
                    oldMethod() {
                        return 'overridden';
                    },
                },
            });

            await expect(ComponentFactory.build('sw-active-major')).resolves.toBeTruthy();
            expect(error).toHaveBeenCalledWith(
                '[Deprecation]',
                expect.stringContaining(
                    'Tried to access deprecated functionality: Component.override(\'sw-active-major\') overrides the deprecated computed "oldFilter"',
                ),
            );
            expect(error).toHaveBeenCalledWith(
                '[Deprecation]',
                expect.stringContaining('overrides the deprecated method "oldMethod"'),
            );
        },
    );

    it.activeFeatureFlags(['v6.8.0.0'])('logs an error instead of throwing for a baselined override', async () => {
        useProductionDeprecationGuard();
        const error = jest.spyOn(console, 'error').mockImplementation(() => {});
        const message =
            'Component.override(\'sw-baselined-major\') overrides the deprecated method "oldMethod", so the override stops working once "oldMethod" is removed with V6_8_0_0. The method is deprecated with: oldMethod is deprecated, use newMethod.';
        deprecationBaseline[message] = { 'sw-baselined-major': ['major'] };
        listedBaselineKeys.push(message);

        registerComponentWithDeprecatedMembers('sw-baselined-major');
        ComponentFactory.override('sw-baselined-major', {
            methods: {
                oldMethod() {
                    return 'overridden';
                },
            },
        });

        await expect(ComponentFactory.build('sw-baselined-major')).resolves.toBeTruthy();
        expect(error).toHaveBeenCalledWith('[Deprecation]', expect.stringContaining(message));
    });
});
