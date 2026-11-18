/**
 * @sw-package fundamentals@after-sales
 * @deprecated tag:v6.8.0 - Will be removed. Use sw-condition-generic instead.
 */
export default {
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
};
