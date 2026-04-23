/**
 * @sw-package inventory
 */
import { mount } from '@vue/test-utils';

describe('src/module/sw-product/component/sw-product-packaging-form', () => {
    async function createWrapper() {
        const store = Shopware.Store.get('swProductDetail');

        store.$reset();
        store.product = {
            purchaseUnit: null,
            unitId: null,
            referenceUnit: null,
            packUnit: null,
            packUnitPlural: null,
        };
        store.parentProduct = {
            id: 'parent-product-id',
            purchaseUnit: 5,
            unitId: 'unit-id',
            referenceUnit: 1,
            packUnit: 'box',
            packUnitPlural: 'boxes',
        };

        return mount(await wrapTestComponent('sw-product-packaging-form', { sync: true }), {
            props: {
                allowEdit: true,
                showSettingPackaging: true,
            },
            global: {
                stubs: {
                    'sw-container': {
                        template: '<div class="sw-container"><slot></slot></div>',
                    },
                    'sw-inherit-wrapper': {
                        props: [
                            'value',
                            'hasParent',
                            'inheritedValue',
                        ],
                        emits: [
                            'update:value',
                        ],
                        computed: {
                            isInherited() {
                                return this.value === null || this.value === undefined;
                            },

                            currentValue() {
                                return this.isInherited ? this.inheritedValue : this.value;
                            },
                        },
                        methods: {
                            updateCurrentValue(value) {
                                this.$emit('update:value', value);
                            },

                            restoreInheritance() {
                                this.$emit('update:value', null);
                            },

                            removeInheritance() {
                                this.$emit('update:value', this.currentValue);
                            },
                        },
                        template: `
                            <div class="sw-inherit-wrapper">
                                <slot name="content" v-bind="{
                                    currentValue,
                                    updateCurrentValue,
                                    isInherited,
                                    isInheritField: hasParent,
                                    restoreInheritance,
                                    removeInheritance
                                }"></slot>
                            </div>`,
                    },
                    'sw-entity-single-select': true,
                    'mt-number-field': {
                        props: [
                            'isInheritanceField',
                            'isInherited',
                            'modelValue',
                        ],
                        emits: [
                            'update:model-value',
                            'inheritance-restore',
                            'inheritance-remove',
                        ],
                        template: `
                            <input
                                class="mt-number-field"
                                :value="modelValue"
                                @input="$emit('update:model-value', $event.target.value)"
                                @click="$emit('inheritance-remove')"
                            />`,
                    },
                    'mt-text-field': true,
                },
            },
        });
    }

    it('should wire inheritance controls for the reference unit field', async () => {
        const wrapper = await createWrapper();
        const store = Shopware.Store.get('swProductDetail');
        const referenceUnitField = wrapper.findComponent('.sw-product-packaging-form__reference-unit-field');

        expect(referenceUnitField.props('isInheritanceField')).toBe(true);
        expect(referenceUnitField.props('isInherited')).toBe(true);

        await referenceUnitField.vm.$emit('inheritance-remove');

        expect(store.product.referenceUnit).toBe(1);

        await referenceUnitField.vm.$emit('inheritance-restore');

        expect(store.product.referenceUnit).toBeNull();
    });
});
