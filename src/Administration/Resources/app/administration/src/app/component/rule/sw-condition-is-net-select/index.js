/**
 * @sw-package fundamentals@after-sales
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
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
