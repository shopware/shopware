import type { Entity } from '@shopware-ag/admin-extension-sdk/es/data/_internals/Entity';
import type { MetaInfo } from 'vue-meta';
import type Repository from 'src/core/data/repository.data';
import type CriteriaType from 'src/core/data/criteria.data';
import template from './sw-settings-tax-provider-detail.html.twig';
import './sw-settings-tax-provider-detail.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

/**
 * @package checkout
 *
 * @private
 */
export default Component.wrapComponentConfig({
    template,

    inject: [
        'repositoryFactory',
        'acl',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        taxProviderId: {
            type: String,
            required: false,
            default: '',
        },
    },

    data(): {
            isLoading: boolean,
            isSaveSuccessful: boolean,
            taxProvider?: Entity<'tax_provider'> | undefined,
            } {
        return {
            taxProvider: undefined as Entity<'tax_provider'> | undefined,
            isLoading: false,
            isSaveSuccessful: false,
        };
    },

    metaInfo(): MetaInfo {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        label(): string {
            return this.taxProvider?.translated?.name || '';
        },

        taxProviderRepository(): Repository<'tax_provider'> {
            return this.repositoryFactory.create('tax_provider');
        },

        allowSave(): boolean {
            return this.acl.can('tax.editor');
        },

        ruleFilter(): CriteriaType {
            const criteria = new Criteria(1, 25);

            criteria.addFilter(Criteria.multi(
                'OR',
                [
                    Criteria.contains('rule.moduleTypes.types', 'tax_provider'),
                    Criteria.equals('rule.moduleTypes', null),
                ],
            ));

            criteria.addSorting(Criteria.sort('name', 'ASC', false));

            return criteria;
        },

        hasIdentifier(): boolean {
            return !!this.taxProvider?.identifier;
        },

        positionIdentifier(): string | undefined {
            if (!this.hasIdentifier) {
                return '';
            }

            const identifier = this.taxProvider?.identifier || '';

            return `sw-settings-tax-tax-provider-detail-custom-${identifier}`;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent(): void {
            void this.loadTaxProvider();
        },

        loadTaxProvider(): Promise<void> {
            this.isLoading = true;

            if (this.taxProviderId) {
                return this.taxProviderRepository.get(this.taxProviderId).then((taxProvider) => {
                    this.taxProvider = taxProvider as Entity<'tax_provider'>;
                    this.isLoading = false;
                });
            }

            this.isLoading = false;

            return Promise.resolve();
        },

        onSave(): Promise<void> {
            this.isSaveSuccessful = false;
            this.isLoading = true;

            if (!this.taxProvider) {
                return Promise.resolve();
            }

            return this.taxProviderRepository.save(this.taxProvider).then(() => {
                this.isSaveSuccessful = true;

                return this.loadTaxProvider();
            }).catch(() => {
                // @ts-expect-error
                // eslint-disable-next-line @typescript-eslint/no-unsafe-call
                this.createNotificationError({
                    message: this.$tc('sw-settings-tax.detail.messageSaveError'),
                });

                this.isLoading = false;
            });
        },

        onCancel(): void {
            void this.$router.push({ name: 'sw.settings.tax.index' });
        },

        onSaveRule(ruleId: string): void {
            if (!this.taxProvider) {
                return;
            }

            this.taxProvider.availabilityRuleId = ruleId;
        },

        onDismissRule(): void {
            if (!this.taxProvider) {
                return;
            }

            this.taxProvider.availabilityRuleId = undefined;
        },
    },
});
