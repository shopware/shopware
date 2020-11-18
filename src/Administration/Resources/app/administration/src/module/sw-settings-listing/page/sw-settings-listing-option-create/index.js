import { kebabCase } from 'lodash';
import '../sw-settings-listing-option-base';
import template from './sw-settings-listing-option-create.html.twig';

Shopware.Component.extend('sw-settings-listing-option-create', 'sw-settings-listing-option-base', {
    template,

    computed: {
        smartBarHeading() {
            return this.productSortingEntity && this.productSortingEntity.label ?
                this.productSortingEntity.label :
                this.$tc('sw-settings-listing.create.smartBarTitle');
        },

        isNewProductSorting() {
            return this.productSortingEntity._isNew;
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.productSortingEntity = this.createProductSortingEntity();
            Shopware.State.commit('context/resetLanguageToDefault');
        },

        createProductSortingEntity() {
            const productSortingEntity = this.productSortingRepository.create(Shopware.Context.api);
            productSortingEntity.fields = [];
            productSortingEntity.priority = 1;
            productSortingEntity.active = false;

            return productSortingEntity;
        },

        onSave() {
            this.productSortingEntity.key = kebabCase(this.productSortingEntity.label);

            return this.productSortingRepository.save(this.productSortingEntity, Shopware.Context.api)
                .then(response => {
                    const encodedResponse = JSON.parse(response.config.data);

                    this.$router.push({ name: 'sw.settings.listing.edit', params: { id: encodedResponse.id } });
                })
                .catch(() => {
                    const sortingOptionName = this.productSortingEntity.label;

                    this.createNotificationError({
                        message: this.$t('sw-settings-listing.base.notification.saveError', { sortingOptionName })
                    });
                });
        },

        onAddCriteria(fieldName) {
            if (!fieldName) {
                return;
            }

            const newCriteria = this.getCriteriaTemplate(fieldName);

            if (!this.productSortingEntity.fields) {
                this.productSortingEntity.fields = [];
            }

            this.productSortingEntity.fields.push(newCriteria);
        },

        onConfirmDeleteCriteria() {
            // filter out criteria
            this.productSortingEntity.fields = this.productSortingEntity.fields.filter(currentCriteria => {
                return currentCriteria.field !== this.toBeDeletedCriteria.field;
            });

            // close delete modal
            this.toBeDeletedCriteria = null;
        }
    }
});
