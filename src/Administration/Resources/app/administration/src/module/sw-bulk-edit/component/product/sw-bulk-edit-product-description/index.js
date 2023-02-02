import template from './sw-bulk-edit-product-description.html.twig';

const { Component } = Shopware;

Component.extend('sw-bulk-edit-product-description', 'sw-text-editor', {
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

                this.$refs.textEditor.innerHTML = '';
                this.content = this.value;
                this.isEmpty = this.emptyCheck(this.content);
                this.placeholderVisible = this.isEmpty;

                this.$nextTick(() => {
                    this.setWordCount();
                    this.setTablesResizable();
                });
            },
        },
    },
});
