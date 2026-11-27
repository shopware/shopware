/**
 * @sw-package framework
 */

import { ComponentFactory } from './native-block-condition.fixtures';

/**
 * Registers `componentName` with a deprecated computed `oldFilter`, a deprecated getter/setter computed
 * `oldValue`, a deprecated method `oldMethod` and the not deprecated computed `filter`.
 */
export function registerComponentWithDeprecatedMembers(componentName) {
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
