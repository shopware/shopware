const { Component } = Shopware;

/**
 * @package business-ops
 */
Component.extend('sw-condition-is-net-select', 'sw-condition-operator-select', {
    computed: {
        operator: {
            get() {
                if (!this.condition.value) {
                    return null;
                }
                return this.condition.value.isNet;
            },
            set(isNet) {
                if (!this.condition.value) {
                    this.condition.value = {};
                }
                this.condition.value = { ...this.condition.value, isNet };
            },
        },
    },
});
