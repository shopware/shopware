import template from './sw-condition-not-found.html.twig';
import './sw-condition-not-found.scss';

const { Component } = Shopware;
const { debounce } = Shopware.Utils;

/**
 * @public
 * @package business-ops
 * @description This condition is shown, if the specific condition was not found.
 * This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-not-found :condition="condition" :level="0"></sw-condition-not-found>
 */
Component.extend('sw-condition-not-found', 'sw-condition-base', {
    template,

    computed: {
        extendedTypes() {
            return [
                {
                    label: this.condition.type,
                    type: this.condition.type,
                },
                ...this.availableTypes,
            ];
        },

        value: {
            get() {
                this.ensureValueExist();
                return JSON.stringify(this.condition.value, null, 4);
            },
            set: debounce(function updateValue(value) {
                try {
                    this.condition.value = JSON.parse(value);
                } catch (e) {
                    /* eslint-ignore-line */
                }
            }, 300),
        },
    },
});
