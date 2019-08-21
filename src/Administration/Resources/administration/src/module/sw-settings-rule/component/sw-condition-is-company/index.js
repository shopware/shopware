import LocalStore from 'src/core/data/LocalStore';
import template from './sw-condition-is-company.html.twig';

const { Component } = Shopware;

/**
 * @public
 * @description Condition for the IsCompanyRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-is-company :condition="condition" :level="0"></sw-condition-is-company>
 */
Component.extend('sw-condition-is-company', 'sw-condition-base', {
    template,

    computed: {
        selectValues() {
            const values = [
                {
                    label: this.$tc('global.sw-condition.condition.yes'),
                    value: 'true'
                },
                {
                    label: this.$tc('global.sw-condition.condition.no'),
                    value: 'false'
                }
            ];

            return new LocalStore(values, 'value');
        },
        fieldNames() {
            return ['isCompany'];
        },
        defaultValues() {
            return {
                isCompany: true
            };
        }
    },

    watch: {
        isCompany: {
            handler(newValue) {
                this.condition.value.isCompany = newValue === 'true';
            }
        }
    },

    data() {
        return {
            isCompany: this.condition.value.isCompany ? String(this.condition.value.isCompany) : String(true)
        };
    }
});
