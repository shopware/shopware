import template from './sw-condition-line-item-novelty.html.twig';

const { Component } = Shopware;
const { mapApiErrors } = Component.getComponentHelper();

Component.extend('sw-condition-line-item-novelty', 'sw-condition-base', {
    template,

    computed: {
        isNovelty: {
            get() {
                this.ensureValueExist();
                return !!this.condition.value.isNovelty;
            },
            set(isNovelty) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, isNovelty };
            }
        },
        trueOption() {
            return { value: true, label: this.$tc('global.sw-condition.condition.yes') };
        },
        falseOption() {
            return { value: false, label: this.$tc('global.sw-condition.condition.no') };
        },

        options() {
            return [this.trueOption, this.falseOption];
        },

        ...mapApiErrors('condition', ['value.isNovelty']),

        currentError() {
            return this.conditionValueAllowedError;
        }
    }
});
