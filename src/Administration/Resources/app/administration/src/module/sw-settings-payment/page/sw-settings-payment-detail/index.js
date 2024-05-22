import template from './sw-settings-payment-detail.html.twig';
import './sw-settings-payment-detail.scss';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { warn } = Shopware.Utils.debug;
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

/**
 * @package checkout
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'acl',
        'customFieldDataProviderService',
        'feature',
    ],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder'),
    ],

    shortcuts: {
        'SYSTEMKEY+S': {
            active() {
                return this.acl.can('payment.editor');
            },
            method: 'onSave',
        },
        ESCAPE: 'onCancel',
    },

    data() {
        return {
            paymentMethod: null,
            mediaItem: null,
            uploadTag: 'sw-payment-method-upload-tag',
            isLoading: false,
            isSaveSuccessful: false,
            customFieldSets: null,
            showDeleteModal: false,
            deletionInProcess: false,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },

    computed: {
        isNewPaymentMethod() {
            return this.paymentMethod._isNew;
        },

        identifier() {
            return this.placeholder(this.paymentMethod, 'name');
        },

        paymentMethodRepository() {
            return this.repositoryFactory.create('payment_method');
        },

        ruleRepository() {
            return this.repositoryFactory.create('rule');
        },

        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        tooltipSave() {
            if (!this.acl.can('payment.editor')) {
                return {
                    message: this.$tc('sw-privileges.tooltip.warning'),
                    disabled: this.acl.can('payment.editor'),
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

        ruleFilter() {
            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.multi(
                'OR',
                [
                    Criteria.contains('rule.moduleTypes.types', 'payment'),
                    Criteria.equals('rule.moduleTypes', null),
                ],
            ));

            criteria.addAssociation('conditions')
                .addSorting(Criteria.sort('name', 'ASC', false));

            return criteria;
        },

        showCustomFields() {
            return this.paymentMethod && this.customFieldSets && this.customFieldSets.length > 0;
        },

        paymentMethodCriteria() {
            const criteria = new Criteria();
            criteria.setIds([this.paymentMethodId]);

            if (this.acl.can('payment.deleter')) {
                criteria.getAssociation('customers').setLimit(1);
                criteria.getAssociation('salesChannels').setLimit(1);
                criteria.getAssociation('salesChannelDefaultAssignments').setLimit(1);
                criteria.getAssociation('orderTransactions').setLimit(1);
            }

            return criteria;
        },


        forbidDelete() {
            return this.paymentMethod.orderTransactions?.length !== 0
                || this.paymentMethod.salesChannels?.length !== 0
                || this.paymentMethod.customers?.length !== 0
                || this.paymentMethod.salesChannelDefaultAssignments?.length !== 0;
        },

        technicalNameIsProvided() {
            return !!this.paymentMethod?.pluginId || !!this.paymentMethod?.appPaymentMethod?.id;
        },

        ...mapPropertyErrors('paymentMethod', ['name', 'technicalName']),
    },

    watch: {
        'paymentMethod.mediaId'() {
            if (this.paymentMethod.mediaId) {
                this.setMediaItem({ targetId: this.paymentMethod.mediaId });
            }
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.paymentMethodId = this.$route.params.id;
            this.loadEntityData();
            this.loadCustomFieldSets();
        },

        onSaveRule(ruleId) {
            this.paymentMethod.availabilityRuleId = ruleId;
        },

        onDismissRule() {
            this.paymentMethod.availabilityRuleId = null;
        },

        abortOnLanguageChange() {
            return this.paymentMethodRepository.hasChanges(this.paymentMethod);
        },

        saveOnLanguageChange() {
            return this.onSave();
        },

        onChangeLanguage() {
            this.loadEntityData();
        },

        loadEntityData() {
            this.isLoading = true;

            this.paymentMethodRepository.search(this.paymentMethodCriteria)
                .then(response => response.first())
                .then((paymentMethod) => {
                    this.paymentMethod = paymentMethod;

                    if (!paymentMethod?.mediaId) {
                        return;
                    }

                    this.setMediaItem({ targetId: paymentMethod.mediaId });
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        loadCustomFieldSets() {
            this.customFieldDataProviderService.getCustomFieldSets('payment_method').then((sets) => {
                this.customFieldSets = sets;
            });
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        onSave() {
            this.isSaveSuccessful = false;
            this.isLoading = true;

            return this.paymentMethodRepository.save(this.paymentMethod)
                .then(() => {
                    this.isSaveSuccessful = true;
                    this.$refs.mediaSidebarItem.getList();
                    this.loadEntityData();
                })
                .catch((exception) => {
                    this.onError(exception);
                    warn(this._name, exception.message, exception.response);
                    throw exception;
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        onError(error) {
            let errorDetails = null;

            try {
                errorDetails = error.response.data.errors[0].detail;
            } catch (e) {
                errorDetails = '';
            }

            this.createNotificationError({
                title: this.$tc('global.default.error'),
                // eslint-disable-next-line max-len
                message: `${this.$tc('sw-settings-payment.detail.messageSaveError', 0, { name: this.paymentMethod.name })} ${errorDetails}`,
            });
        },

        onCancel() {
            this.$router.push({ name: 'sw.settings.payment.overview' });
        },

        setMediaItem({ targetId }) {
            this.mediaRepository.get(targetId)
                .then((updatedMedia) => {
                    this.mediaItem = updatedMedia;
                    this.paymentMethod.mediaId = targetId;
                });
        },

        setMediaFromSidebar(mediaEntity) {
            this.mediaItem = mediaEntity;
            this.paymentMethod.mediaId = mediaEntity.id;
        },

        onUnlinkLogo() {
            this.mediaItem = null;
            this.paymentMethod.mediaId = null;
        },

        onDropMedia(mediaItem) {
            this.setMediaItem({ targetId: mediaItem.id });
        },

        openMediaSidebar() {
            this.$refs.mediaSidebarItem.openContent();
        },

        async deletePaymentMethod() {
            if (!this.acl.can('payment.deleter')) return;

            this.deletionInProcess = true;

            await this.paymentMethodRepository.delete(this.paymentMethod.id);
            await this.$router.replace({ name: 'sw.settings.payment.overview' });
        },
    },
};
