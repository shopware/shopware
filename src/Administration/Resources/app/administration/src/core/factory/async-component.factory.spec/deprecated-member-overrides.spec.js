/**
 * @sw-package framework
 */

import deprecationBaseline from 'src/core/deprecation-baseline.json';
import { deprecations, useProductionDeprecationGuard } from 'test/_helper_/deprecations';
import { ComponentFactory, mount, setupComponentFactoryHooks } from './native-block-condition.fixtures';

describe('core/factory/async-component.factory.ts - overrides of deprecated members', () => {
    setupComponentFactoryHooks();

    const listedBaselineKeys = [];

    afterEach(() => {
        listedBaselineKeys.splice(0).forEach((key) => {
            delete deprecationBaseline[key];
        });
    });

    /**
     * Registers `componentName` with a deprecated computed `oldFilter`, a deprecated getter/setter computed
     * `oldValue`, a deprecated method `oldMethod` and the not deprecated computed `filter`.
     */
    function registerComponentWithDeprecatedMembers(componentName) {
        ComponentFactory.register(componentName, {
            template: '<div>{{ oldFilter }}</div>',
            computed: {
                filter() {
                    return 'filter';
                },

                /**
                 * @deprecated tag:v6.8.0 - Will be removed, use `filter` instead.
                 */
                oldFilter() {
                    Shopware.Feature.triggerDeprecationOrThrow(
                        'V6_8_0_0',
                        `${this.$options.name}.oldFilter is deprecated, use filter instead.`,
                    );

                    return 'oldFilter';
                },

                /**
                 * @deprecated tag:v6.8.0 - Will be removed, use `value` instead.
                 */
                oldValue: {
                    get() {
                        Shopware.Feature.triggerDeprecationOrThrow('V6_8_0_0', 'oldValue is deprecated.');

                        return this._oldValue;
                    },
                    set(value) {
                        Shopware.Feature.triggerDeprecationOrThrow('V6_8_0_0', 'oldValue is deprecated.');

                        this._oldValue = value;
                    },
                },
            },
            methods: {
                /**
                 * @deprecated tag:v6.8.0 - Will be removed, use `newMethod` instead.
                 */
                oldMethod() {
                    Shopware.Feature.triggerDeprecationOrThrow('V6_8_0_0', 'oldMethod is deprecated, use newMethod.');

                    return 'oldMethod';
                },
            },
        });
    }

    it('reports an override that replaces a deprecated computed without $super', async () => {
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
            'Component.override(\'sw-replaced-computed\') replaces the deprecated computed "oldFilter" without calling this.$super(), so the override stops working once "oldFilter" is removed with V6_8_0_0.',
        );
    });

    it('reports an override that replaces a deprecated method without $super, with the message of its guard', async () => {
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
            'replaces the deprecated method "oldMethod" without calling this.$super(), so the override stops working once "oldMethod" is removed with V6_8_0_0. The method is deprecated with: oldMethod is deprecated, use newMethod.',
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

    it('reports only the guard of the member for an override that calls $super', async () => {
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
        expect(deprecations).toHaveTriggered('sw-super-computed.oldFilter is deprecated, use filter instead.');
        expect(deprecations).not.toHaveTriggered('Component.override');
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
    ])('reports an override that replaces the %s of a deprecated getter/setter computed', async (_, oldValue) => {
        registerComponentWithDeprecatedMembers('sw-replaced-accessor');
        ComponentFactory.override('sw-replaced-accessor', {
            computed: { oldValue },
        });

        await ComponentFactory.build('sw-replaced-accessor');

        expect(deprecations).toHaveTriggered(
            'replaces the deprecated computed "oldValue" without calling this.$super(), so the override stops working once "oldValue" is removed with V6_8_0_0. The computed is deprecated with: oldValue is deprecated.',
        );
    });

    it('does not report an override that calls $super in the getter and the setter', async () => {
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

        expect(deprecations.entries).toHaveLength(0);
    });

    it('reports an override that replaces a deprecated member its component inherits', async () => {
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

        expect(deprecations).toHaveTriggered(
            'Component.override(\'sw-child\') replaces the deprecated method "oldMethod" without calling this.$super()',
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

    /**
     * @deprecated tag:v6.8.0 - Feature.silence() silences nothing once V6_8_0_0 is active.
     */
    it.deprecated('v6.8.0.0')(
        'does not report an override registered inside Feature.silence() of the major of the member',
        async () => {
            registerComponentWithDeprecatedMembers('sw-silenced-override');
            Shopware.Feature.silence('V6_8_0_0', () => {
                ComponentFactory.override('sw-silenced-override', {
                    methods: {
                        oldMethod() {
                            return 'overridden';
                        },
                    },
                });
            });

            await ComponentFactory.build('sw-silenced-override');

            expect(deprecations.entries).toHaveLength(0);
        },
    );

    it('reports an override registered inside Feature.silence() of another major', async () => {
        registerComponentWithDeprecatedMembers('sw-other-major-silenced');
        Shopware.Feature.silence('V6_9_0_0', () => {
            ComponentFactory.override('sw-other-major-silenced', {
                methods: {
                    oldMethod() {
                        return 'overridden';
                    },
                },
            });
        });

        await ComponentFactory.build('sw-other-major-silenced');

        expect(deprecations).toHaveTriggered(
            'Component.override(\'sw-other-major-silenced\') replaces the deprecated method "oldMethod"',
        );
    });

    it.activeFeatureFlags(['v6.8.0.0'])(
        'throws for an override registered inside Feature.silence() once the major flag is active',
        async () => {
            useProductionDeprecationGuard();
            registerComponentWithDeprecatedMembers('sw-silenced-active-major');
            Shopware.Feature.silence('V6_8_0_0', () => {
                ComponentFactory.override('sw-silenced-active-major', {
                    methods: {
                        oldMethod() {
                            return 'overridden';
                        },
                    },
                });
            });

            await expect(ComponentFactory.build('sw-silenced-active-major')).rejects.toThrow(
                'Tried to access deprecated functionality: Component.override(\'sw-silenced-active-major\') replaces the deprecated method "oldMethod"',
            );
        },
    );

    it.activeFeatureFlags(['v6.8.0.0'])('throws once the major flag is active', async () => {
        useProductionDeprecationGuard();
        registerComponentWithDeprecatedMembers('sw-active-major');
        ComponentFactory.override('sw-active-major', {
            methods: {
                oldMethod() {
                    return 'overridden';
                },
            },
        });

        await expect(ComponentFactory.build('sw-active-major')).rejects.toThrow(
            'Tried to access deprecated functionality: Component.override(\'sw-active-major\') replaces the deprecated method "oldMethod"',
        );
    });

    it.activeFeatureFlags(['v6.8.0.0'])('logs an error instead of throwing for a baselined override', async () => {
        useProductionDeprecationGuard();
        const error = jest.spyOn(console, 'error').mockImplementation(() => {});
        const message =
            'Component.override(\'sw-baselined-major\') replaces the deprecated method "oldMethod" without calling this.$super(), so the override stops working once "oldMethod" is removed with V6_8_0_0. The method is deprecated with: oldMethod is deprecated, use newMethod.';
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
