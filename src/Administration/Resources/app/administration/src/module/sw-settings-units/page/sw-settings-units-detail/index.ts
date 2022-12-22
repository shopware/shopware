import type { Entity } from '@shopware-ag/admin-extension-sdk/es/data/_internals/Entity';
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

        ...mapPropertyErrors('unit', ['name', 'shortCode']),
    },

    data(): { unit: Entity<'unit'>|null, isLoading: boolean, isSaveSuccessful: boolean } {
        return {
            unit: null,
            isLoading: true,
            isSaveSuccessful: false,
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
        if (this.unitId !== null) {
            this.loadUnit();

            return;
        }

        this.unit = this.unitRepository.create(Shopware.Context.api);
        this.isLoading = false;
    },

    methods: {
        loadUnit(): void {
            this.isLoading = true;

            this.unitRepository.get(this.unitId, Shopware.Context.api).then((unit) => {
                this.unit = unit;

                this.isLoading = false;
            }).catch((error: { message: string }) => {
                // @ts-expect-error - Mixin methods are not recognized
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
            this.unitRepository.save(this.unit, Shopware.Context.api).then(() => {
                this.isSaveSuccessful = true;

                void this.$router.push({ name: 'sw.settings.units.detail', params: { id: this.unit?.id ?? '' } });

                this.isLoading = false;
            }).catch(() => {
                // @ts-expect-error - Mixin methods are not recognized
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
