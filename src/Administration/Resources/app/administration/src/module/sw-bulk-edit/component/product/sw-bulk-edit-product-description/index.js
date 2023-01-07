/**
 * @package system-settings
 */
import template from './sw-bulk-edit-product-description.html.twig';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    watch: {
        value: {
            handler() {
                if (!this.$refs.textEditor) {
                    return;
                }
                if (!this.value) {
                    return;
                }

                this.isEmpty = this.emptyCheck(this.content);
                this.placeholderVisible = this.isEmpty;

                this.$nextTick(() => {
                    this.setWordCount();
                    this.setTablesResizable();
                });
            },
        },
    },
};
