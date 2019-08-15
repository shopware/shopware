import template from './sw-country-state-detail.html.twig';

const { Component, Mixin } = Shopware;

Component.register('sw-country-state-detail', {
    template,

    mixins: [
        Mixin.getByName('placeholder')
    ],

    props: {
        countryState: {
            type: Object,
            required: true
        }
    },

    computed: {
        modalTitle() {
            if (this.countryState.isNew()) {
                return this.$tc('sw-country-state-detail.titleNew');
            }

            return this.$tc('sw-country-state-detail.titleEdit');
        }
    },

    methods: {
        onCancel() {
            this.$emit('attribute-edit-cancel', this.countryState);
        },
        onSave() {
            this.$emit('attribute-edit-save', this.countryState);
        }
    }
});
