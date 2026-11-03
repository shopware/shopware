/*
 * @sw-package inventory
 */

import template from './sw-property-create.html.twig';
import { getApiContext, getAssetFilter, useContextStore } from '../dependencies';

/**
 * @public
 */
export default {
    template,

    data() {
        return {
            newId: null,
        };
    },

    computed: {
        assetFilter() {
            return getAssetFilter();
        },
    },

    methods: {
        createdComponent() {
            if (!useContextStore().isSystemDefaultLanguage) {
                getApiContext().languageId = getApiContext().systemLanguageId;
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
            this.$router.push({
                name: 'sw.property.detail',
                params: { id: this.newId },
            });
        },

        onSave() {
            this.$super('onSave');
        },
    },
};
