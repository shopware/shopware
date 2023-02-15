import type { PropType } from 'vue';
import type { Entity } from '@shopware-ag/admin-extension-sdk/es/data/_internals/Entity';
import type Repository from 'src/core/data/repository.data';
import type EntityCollection from '@shopware-ag/admin-extension-sdk/es/data/_internals/EntityCollection';
import template from './sw-settings-tax-provider-sorting-modal.html.twig';
import './sw-settings-tax-provider-sorting-modal.scss';

const { Component, Mixin } = Shopware;

/**
 * @package checkout
 *
 * @private
 */
export default Component.wrapComponentConfig({
    template,

    inject: [
        'acl',
        'repositoryFactory',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        taxProviders: {
            type: Array as unknown as PropType<EntityCollection<'tax_provider'>>,
            required: true,
        },
    },

    data(): {
            isSaving: boolean,
            originalTaxProviders: EntityCollection<'tax_provider'>,
            sortedTaxProviders: EntityCollection<'tax_provider'>,
            } {
        return {
            isSaving: false,
            originalTaxProviders: this.taxProviders,
            sortedTaxProviders: this.taxProviders,
        };
    },

    computed: {
        taxProviderRepository(): Repository<'tax_provider'> {
            return this.repositoryFactory.create('tax_provider');
        },
    },

    methods: {
        closeModal(): void {
            this.$emit('modal-close');
        },

        applyChanges(): void {
            this.isSaving = true;

            this.sortedTaxProviders.map((taxProvider: Entity<'tax_provider'>, index: number) => {
                taxProvider.priority = this.sortedTaxProviders.length - index;
                return taxProvider;
            });

            this.taxProviderRepository.saveAll(this.sortedTaxProviders)
                .then(() => {
                    this.isSaving = false;
                    this.$emit('modal-close');
                    this.$emit('modal-save');

                    // @ts-expect-error
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-call
                    this.createNotificationSuccess({
                        message: this.$tc('sw-settings-tax.list.taxProvider.sorting-modal.saveSuccessful'),
                    });
                })
                .catch(() => {
                    // @ts-expect-error
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-call
                    this.createNotificationError({
                        message: this.$tc('sw-settings-tax.list.taxProvider.sorting-modal.errorMessage'),
                    });
                });
        },

        onSort(sortedItems: EntityCollection<'tax_provider'>): void {
            this.sortedTaxProviders = sortedItems;
        },
    },
});
