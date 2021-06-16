import template from './sw-settings-salutation-detail.html.twig';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const ShopwareError = Shopware.Classes.ShopwareError;
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();
const utils = Shopware.Utils;

Component.register('sw-settings-salutation-detail', {
    template,

    inject: ['repositoryFactory', 'acl', 'customFieldDataProviderService'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder'),
        Mixin.getByName('discard-detail-page-changes')('salutation'),
    ],

    props: {
        salutationId: {
            type: String,
            required: false,
            default: null,
        },
    },

    shortcuts: {
        'SYSTEMKEY+S': {
            active() {
                return this.allowSave;
            },
            method: 'onSave',
        },

        ESCAPE: 'onCancel',
    },

    data() {
        return {
            entityName: 'salutation',
            isLoading: false,
            salutation: null,
            invalidKey: false,
            isKeyChecking: false,
            isSaveSuccessful: false,
            customFieldSets: null,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },

    computed: {
        identifier() {
            return this.placeholder(this.salutation, 'displayName');
        },

        salutationRepository() {
            return this.repositoryFactory.create('salutation');
        },

        entityDescription() {
            return this.placeholder(
                this.salutation,
                'salutationKey',
                this.$tc('sw-settings-salutation.detail.placeholderNewSalutation'),
            );
        },

        invalidKeyError() {
            if (this.invalidKey && !this.isKeyChecking) {
                return new ShopwareError({ code: 'DUPLICATED_SALUTATION_KEY' });
            }
            return null;
        },

        allowSave() {
            return this.salutation && this.salutation.isNew()
                ? this.acl.can('salutation.creator')
                : this.acl.can('salutation.editor');
        },

        tooltipSave() {
            if (!this.allowSave) {
                return {
                    message: this.$tc('sw-privileges.tooltip.warning'),
                    disabled: this.allowSave,
                    showOnDisabledElements: true,
                };
            }

            const systemKey = this.$device.getSystemKey();

            return {
                message: `${systemKey} + S`,
                appearance: 'light',
            };
        },

        tooltipCancel() {
            return {
                message: 'ESC',
                appearance: 'light',
            };
        },

        ...mapPropertyErrors('salutation', ['displayName', 'letterName']),

        showCustomFields() {
            return this.salutation && this.customFieldSets && this.customFieldSets.length > 0;
        },
    },

    watch: {
        salutationId() {
            if (!this.salutationId) {
                this.createdComponent();
            }
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.isLoading = true;
            if (this.salutationId) {
                this.salutationRepository.get(this.salutationId).then((salutation) => {
                    this.salutation = salutation;
                    this.isLoading = false;
                });
                this.loadCustomFieldSets();
                return;
            }

            Shopware.State.commit('context/resetLanguageToDefault');
            this.salutation = this.salutationRepository.create();
            this.isLoading = false;
        },

        loadCustomFieldSets() {
            this.customFieldDataProviderService.getCustomFieldSets('salutation').then((sets) => {
                this.customFieldSets = sets;
            });
        },

        onChangeLanguage() {
            this.createdComponent();
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        onSave() {
            this.isLoading = true;
            this.isSaveSuccessful = false;

            return this.salutationRepository.save(this.salutation).then(() => {
                this.isSaveSuccessful = true;
                if (!this.salutationId) {
                    this.$router.push({ name: 'sw.settings.salutation.detail', params: { id: this.salutation.id } });
                }

                this.salutationRepository.get(this.salutation.id).then((updatedSalutation) => {
                    this.salutation = updatedSalutation;
                    this.isLoading = false;
                });
            }).catch(() => {
                this.createNotificationError({
                    title: this.$tc('global.default.error'),
                    message: this.$tc('sw-settings-salutation.detail.notificationErrorMessage'),
                });
            });
        },

        onCancel() {
            this.$router.push({ name: 'sw.settings.salutation.index' });
        },

        onChange() {
            this.isKeyChecking = true;
            this.onChangeDebounce();
        },

        onChangeDebounce: utils.debounce(function executeChange() {
            if (!this.salutation) {
                return;
            }

            if (typeof this.salutation.salutationKey !== 'string' ||
                this.salutation.salutationKey.trim() === ''
            ) {
                this.invalidKey = false;
                this.isKeyChecking = false;
                return;
            }

            const criteria = new Criteria(1, 1);
            criteria.addFilter(
                Criteria.multi(
                    'AND',
                    [
                        Criteria.equals('salutationKey', this.salutation.salutationKey),
                        Criteria.not('AND', [Criteria.equals('id', this.salutation.id)]),
                    ],
                ),
            );

            this.salutationRepository.search(criteria).then(({ total }) => {
                this.invalidKey = total > 0;
                this.isKeyChecking = false;
            }).catch(() => {
                this.invalidKey = true;
                this.isKeyChecking = false;
            });
        }, 500),
    },
});
