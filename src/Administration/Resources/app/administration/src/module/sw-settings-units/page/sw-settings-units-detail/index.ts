/**
 * @package inventory
 */
import type { Entity } from '@shopware-ag/meteor-admin-sdk/es/data/_internals/Entity';
import Criteria from '@shopware-ag/meteor-admin-sdk/es/data/Criteria';
import template from './index.html.twig';
import type Repository from '../../../../core/data/repository.data';
import { mapPropertyErrors } from '../../../../app/service/map-errors.service';

const { Component, Mixin } = Shopware;

/**
 * @private
 */
export default Component.wrapComponentConfig({
    template,

    mixins: [
        Mixin.getByName('notification'),
    ],


    inject: ['repositoryFactory', 'acl'],


    props: {
        /**
         * Either the id of the unit when in edit mode or null when in create mode.
         */
        unitId: {
            type: String,
            required: false,
            default: null,
        },
    },

    computed: {
        unitRepository(): Repository<'unit'> {
            return this.repositoryFactory.create('unit');
        },

        customFieldSetRepository(): Repository<'custom_field_set'> {
            return this.repositoryFactory.create('custom_field_set');
        },

        customFieldSetCriteria(): Criteria {
            const criteria = new Criteria(1, null);
            criteria.addFilter(Criteria.equals('relations.entityName', 'unit'));

            return criteria;
        },

        ...mapPropertyErrors('unit', ['name', 'shortCode']),
    },

    data(): {
        unit: Entity<'unit'>|null,
        isLoading: boolean,
        isSaveSuccessful: boolean,
        customFieldSets: Entity<'custom_field_set'>[]
        } {
        return {
            unit: null,
            isLoading: true,
            isSaveSuccessful: false,
            customFieldSets: [],
        };
    },

    watch: {
        unitId() {
            this.loadUnit();
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
        this.customFieldSetRepository.search(this.customFieldSetCriteria).then((result) => {
            this.customFieldSets = result;

            if (this.unitId !== null) {
                this.loadUnit();

                return;
            }

            this.unit = this.unitRepository.create(Shopware.Context.api);
            this.isLoading = false;
        }).catch(() => {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call
            this.createNotificationError({
                message: this.$tc('sw-settings-units.notification.errorMessage'),
            });

            this.isLoading = false;
        });
    },

    methods: {
        loadUnit(): void {
            this.isLoading = true;

            this.unitRepository.get(this.unitId, Shopware.Context.api).then((unit) => {
                this.unit = unit;

                this.isLoading = false;
            }).catch((error: { message: string }) => {
                // eslint-disable-next-line @typescript-eslint/no-unsafe-call
                this.createNotificationError({
                    message: this.$tc(error.message),
                });
            });
        },

        onSave(): void {
            if (this.unit === null) {
                return;
            }

            this.isLoading = true;
            this.unitRepository.save(this.unit).then(() => {
                this.isSaveSuccessful = true;

                void this.$router.push({ name: 'sw.settings.units.detail', params: { id: this.unit?.id ?? '' } });

                this.isLoading = false;
            }).catch(() => {
                // eslint-disable-next-line @typescript-eslint/no-unsafe-call
                this.createNotificationError({
                    message: this.$tc('sw-settings-units.notification.errorMessage'),
                });

                this.isLoading = false;
            });
        },

        onChangeLanguage(): void {
            this.loadUnit();
        },
    },
});
