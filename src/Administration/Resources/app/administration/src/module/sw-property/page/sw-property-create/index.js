import template from './sw-property-create.html.twig';
import './sw-property-create.scss';

const { Component } = Shopware;

Component.extend('sw-property-create', 'sw-property-detail', {
    template,

    data() {
        return {
            newId: null
        };
    },

    methods: {
        createdComponent() {
            if (!Shopware.State.getters['context/isSystemDefaultLanguage']) {
                Shopware.Context.api.languageId = Shopware.Context.api.systemLanguageId;
            }

            this.propertyGroup = this.propertyRepository.create(Shopware.Context.api);
            this.propertyGroup.sortingType = 'alphanumeric';
            this.propertyGroup.displayType = 'text';
            this.propertyGroup.position = 1;
            this.newId = this.propertyGroup.id;

            this.isLoading = false;
        },

        saveFinish() {
            this.isSaveSuccessful = false;
            this.$router.push({ name: 'sw.property.detail', params: { id: this.newId } });
        },

        onSave() {
            this.$super('onSave');
        }
    }
});
