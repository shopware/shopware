/*
 * @package inventory
 */

import template from './sw-property-create.html.twig';
import './sw-property-create.scss';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    data() {
        return {
            newId: null,
        };
    },

    methods: {
        createdComponent() {
            if (!Shopware.State.getters['context/isSystemDefaultLanguage']) {
                Shopware.Context.api.languageId = Shopware.Context.api.systemLanguageId;
            }

            this.propertyGroup = this.propertyRepository.create();
            this.propertyGroup.sortingType = 'alphanumeric';
            this.propertyGroup.displayType = 'text';
            this.propertyGroup.position = 1;
            this.propertyGroup.filterable = true;
            this.propertyGroup.visibleOnProductDetailPage = true;
            this.newId = this.propertyGroup.id;

            this.isLoading = false;
        },

        saveFinish() {
            this.isSaveSuccessful = false;
            this.$router.push({ name: 'sw.property.detail', params: { id: this.newId } });
        },

        onSave() {
            this.$super('onSave');
        },
    },
};
