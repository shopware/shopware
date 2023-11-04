/**
 * @package sales-channel
 */

import template from './sw-mail-header-footer-detail.html.twig';
import './sw-mail-header-footer-detail.scss';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { warn } = Shopware.Utils.debug;
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['entityMappingService', 'repositoryFactory', 'acl'],

    mixins: [
        Mixin.getByName('placeholder'),
        Mixin.getByName('notification'),
    ],

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
            mailHeaderFooter: null,
            mailHeaderFooterId: null,
            isLoading: true,
            isSaveSuccessful: false,
            editorConfig: {
                enableBasicAutocompletion: true,
            },
            showModal: false,
            alreadyAssignedSalesChannels: [],
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },

    computed: {
        ...mapPropertyErrors('mailHeaderFooter', [
            'name',
        ]),

        identifier() {
            return this.placeholder(this.mailHeaderFooter, 'name');
        },

        mailHeaderFooterRepository() {
            return this.repositoryFactory.create('mail_header_footer');
        },

        mailHeaderFooterCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addAssociation('salesChannels');

            return criteria;
        },

        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },

        completerFunction() {
            return (function completerWrapper(entityMappingService) {
                function completerFunction(prefix) {
                    const properties = [];
                    Object.keys(
                        entityMappingService.getEntityMapping(prefix, { salesChannel: 'sales_channel' }),
                    ).forEach((val) => {
                        properties.push({
                            value: val,
                        });
                    });
                    return properties;
                }
                return completerFunction;
            }(this.entityMappingService));
        },

        allowSave() {
            return this.mailHeaderFooter && this.mailHeaderFooter.isNew()
                ? this.acl.can('mail_templates.creator')
                : this.acl.can('mail_templates.editor');
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
    },

    watch: {
        '$route.params.id'() {
            this.createdComponent();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        onClose() {
            this.showModal = false;
            this.isLoading = false;
        },

        async createdComponent() {
            if (this.$route.params.id) {
                this.mailHeaderFooterId = this.$route.params.id;
                await this.loadEntityData();
            }

            this.isLoading = false;
        },

        async loadEntityData() {
            this.isLoading = true;

            this.mailHeaderFooter = await this.mailHeaderFooterRepository.get(
                this.mailHeaderFooterId,
                Shopware.Context.api,
                this.mailHeaderFooterCriteria,
            );

            this.isLoading = false;
        },

        abortOnLanguageChange() {
            return this.this.mailHeaderFooterRepository.hasChanges(this.mailHeaderFooter);
        },

        saveOnLanguageChange() {
            return this.onSave();
        },

        onChangeLanguage() {
            this.loadEntityData();
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        onCancel() {
            this.$router.push({ name: 'sw.mail.template.index' });
        },

        async onSave() {
            this.isSaveSuccessful = false;
            this.isLoading = true;

            if (this.mailHeaderFooter.salesChannels.length > 0) {
                await this.findAlreadyAssignedSalesChannels();
            }

            if (this.alreadyAssignedSalesChannels.length) {
                this.showModal = true;
                this.isLoading = false;

                return;
            }

            await this.confirmSave();
        },

        async confirmSave() {
            try {
                this.isLoading = true;

                await this.mailHeaderFooterRepository.save(this.mailHeaderFooter);
                await this.loadEntityData();

                this.isSaveSuccessful = true;
            } catch (error) {
                const notificationError = {
                    message: this.$tc(
                        'global.notification.notificationSaveErrorMessageRequiredFieldsInvalid',
                    ),
                };

                this.createNotificationError(notificationError);
                warn(error);
            } finally {
                this.isLoading = false;
                this.showModal = false;
            }
        },

        async findAlreadyAssignedSalesChannels() {
            const criteria = new Criteria(1, 25);
            const salesChannelIds = [];

            this.mailHeaderFooter.salesChannels.forEach(salesChannel => {
                salesChannelIds.push(salesChannel.id);
            });

            criteria.addFilter(Criteria.equalsAny('id', salesChannelIds));

            const items = await this.salesChannelRepository.search(criteria);
            this.alreadyAssignedSalesChannels = items.reduce((assignedSalesChannels, currentSalesChannel) => {
                if (currentSalesChannel.mailHeaderFooterId === null) {
                    return assignedSalesChannels;
                }

                if (!this.mailHeaderFooterId) {
                    assignedSalesChannels.push(currentSalesChannel);
                }

                if (this.mailHeaderFooterId && currentSalesChannel.mailHeaderFooterId !== this.mailHeaderFooterId) {
                    assignedSalesChannels.push(currentSalesChannel);
                }

                return assignedSalesChannels;
            }, []);
        },
    },
};
