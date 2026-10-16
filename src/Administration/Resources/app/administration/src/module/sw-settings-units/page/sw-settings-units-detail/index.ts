/**
 * @sw-package inventory
 */
import Criteria from '@shopware-ag/meteor-admin-sdk/es/data/Criteria';
import template from './sw-settings-units-detail.html.twig';
import type Repository from '../../../../core/data/repository.data';
import { mapPropertyErrors } from '../../../../app/service/map-errors.service';
import useNotification from '../../../../app/composables/use-notification';

type CustomFieldDataProvider = {
    getCustomFieldSets(
        entityName: string,
        forceReload: boolean,
        limit: number | null,
    ): Promise<Entity<'custom_field_set'>[]>;
};

/**
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    inject: ['repositoryFactory', 'acl', 'customFieldDataProviderService'],

    props: {
        /**
         * Either the id of the unit when in edit mode or null when in create mode.
         */
        unitId: {
            type: [String, null] as PropType<EntityKey<'unit'> | null>,
            required: false,
            default: null,
        },
    },

    setup() {
        const { createNotificationError } = useNotification();

        return { createNotificationError };
    },

    data(): {
        unit: Entity<'unit'> | null;
        isLoading: boolean;
        isSaveSuccessful: boolean;
        customFieldSets: Entity<'custom_field_set'>[];
    } {
        return {
            unit: null,
            isLoading: true,
            isSaveSuccessful: false,
            customFieldSets: [],
        };
    },

    computed: {
        unitRepository(): Repository<'unit'> {
            return this.repositoryFactory.create('unit');
        },

        ...mapPropertyErrors('unit', ['name', 'shortCode']),

        // @deprecated tag:v6.8.0 - Use customFieldDataProviderService instead.
        customFieldSetRepository(): Repository<'custom_field_set'> {
            return this.repositoryFactory.create('custom_field_set');
        },

        // @deprecated tag:v6.8.0 - Use customFieldDataProviderService instead.
        customFieldSetCriteria(): Criteria {
            const criteria = new Criteria(1, null);
            criteria.addFilter(Criteria.equals('relations.entityName', 'unit'));

            return criteria;
        },
    },

    watch: {
        unitId() {
            void this.loadUnit();
        },

        isSaveSuccessful(newValue) {
            if (newValue === false) {
                return;
            }

            window.setTimeout(() => {
                this.isSaveSuccessful = false;
            }, 800);
        },
    },

    created() {
        void this.createdComponent();
    },

    methods: {
        async createdComponent(): Promise<void> {
            const customFieldDataProvider = this.customFieldDataProviderService as CustomFieldDataProvider;

            try {
                this.customFieldSets = await customFieldDataProvider.getCustomFieldSets('unit', false, null);
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-settings-units.notification.errorMessage'),
                });
                this.isLoading = false;

                return;
            }

            if (this.unitId !== null) {
                await this.loadUnit();

                return;
            }

            this.unit = this.unitRepository.create(Shopware.Context.api);
            this.isLoading = false;
        },

        async loadUnit(): Promise<void> {
            if (this.unitId === null) {
                return;
            }

            this.isLoading = true;

            try {
                this.unit = await this.unitRepository.get(this.unitId, Shopware.Context.api);
            } catch (error) {
                this.createNotificationError({
                    message: this.$t((error as Error).message),
                });
            } finally {
                this.isLoading = false;
            }
        },

        async onSave(): Promise<void> {
            if (this.unit === null) {
                return;
            }

            this.isLoading = true;

            try {
                await this.unitRepository.save(this.unit, Shopware.Context.api);
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-settings-units.notification.errorMessage'),
                });
                this.isLoading = false;

                return;
            }

            this.isSaveSuccessful = true;

            if (this.unit.id === this.unitId) {
                // Reloading resets the origin of the entity, so the next save only sends new changes
                await this.loadUnit();

                return;
            }

            this.isLoading = false;
            // The route change sets the unitId prop, whose watcher loads the saved unit
            await this.$router.push({
                name: 'sw.settings.units.detail',
                params: { id: this.unit.id },
            });
        },

        onChangeLanguage(): void {
            void this.loadUnit();
        },
    },
});
