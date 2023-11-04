/**
 * @package system-settings
 */
import template from './sw-settings-custom-field-set-detail.html.twig';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'acl',
    ],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('sw-inline-snippet'),
        Mixin.getByName('discard-detail-page-changes')('set'),
    ],

    shortcuts: {
        'SYSTEMKEY+S': {
            active() {
                return this.acl.can('custom_field.editor');
            },
            method: 'onSave',
        },
        ESCAPE: 'onCancel',
    },

    data() {
        return {
            set: {},
            setId: '',
            isLoading: true,
            isSaveSuccessful: false,
            technicalNameError: null,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },

    computed: {
        identifier() {
            return this.set.config && this.getInlineSnippet(this.set.config.label)
                ? this.getInlineSnippet(this.set.config.label)
                : this.set.name;
        },

        customFieldSetRepository() {
            return this.repositoryFactory.create('custom_field_set');
        },

        customFieldRepository() {
            return this.repositoryFactory.create('custom_field');
        },

        customFieldCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.equals('customFieldSetId', this.setId));

            return criteria;
        },

        customFieldSetCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addAssociation('relations');

            return criteria;
        },

        tooltipSave() {
            if (!this.acl.can('custom_field.editor')) {
                return {
                    message: this.$tc('sw-privileges.tooltip.warning'),
                    disabled: this.acl.can('custom_field.editor'),
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
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (this.$route.params.id) {
                this.setId = this.$route.params.id;
                this.loadEntityData();
            }
        },

        async loadEntityData() {
            this.set = await this.customFieldSetRepository.get(
                this.setId,
                Shopware.Context.api,
                this.customFieldSetCriteria,
            );
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        onSave() {
            const setLabel = this.identifier;
            const titleSaveSuccess = this.$tc('global.default.success');
            const messageSaveSuccess = this.$tc('sw-settings-custom-field.set.detail.messageSaveSuccess', 0, {
                name: setLabel,
            });
            this.isSaveSuccessful = false;
            this.isLoading = true;

            // Remove all translations except for default locale(fallbackLanguage)
            // in case, the set is not translated
            if (!this.set.config.translated || this.set.config.translated === false) {
                const fallbackLocale = this.swInlineSnippetFallbackLocale;
                this.set.config.label = { [fallbackLocale]: this.set.config.label[fallbackLocale] };
            }

            if (!this.set.relations) {
                this.set.relations = [];
            }

            this.customFieldSetRepository.save(this.set).then(() => {
                this.isSaveSuccessful = true;

                this.createNotificationSuccess({
                    title: titleSaveSuccess,
                    message: messageSaveSuccess,
                });

                return this.loadEntityData();
            }).catch((error) => {
                const errorMessage = error?.response?.data?.errors?.[0]?.detail ?? 'Error';

                this.createNotificationError({
                    message: errorMessage,
                });
            }).finally(() => {
                this.isLoading = false;
            });
        },

        onCancel() {
            this.$router.push({ name: 'sw.settings.custom.field.index' });
        },

        abortOnLanguageChange() {
            return this.customFieldSetRepository.hasChanges(this.set);
        },

        saveOnLanguageChange() {
            return this.onSave();
        },

        onChangeLanguage() {
            this.loadEntityData();
        },

        onLoadingChanged(loading) {
            this.isLoading = loading;
        },

        onResetErrors() {
            this.technicalNameError = null;
        },
    },
};
