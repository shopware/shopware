/*
 * @package inventory
 */

import template from './sw-manufacturer-detail.html.twig';
import './sw-manufacturer-detail.scss';

const { Mixin, Data: { Criteria } } = Shopware;

const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'acl'],

    mixins: [
        Mixin.getByName('placeholder'),
        Mixin.getByName('notification'),
        Mixin.getByName('discard-detail-page-changes')('manufacturer'),
    ],

    shortcuts: {
        'SYSTEMKEY+S': 'onSave',
        ESCAPE: 'onCancel',
    },

    props: {
        manufacturerId: {
            type: String,
            required: false,
            default: null,
        },
    },


    data() {
        return {
            manufacturer: null,
            customFieldSets: [],
            isLoading: false,
            isSaveSuccessful: false,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },

    computed: {
        identifier() {
            return this.placeholder(this.manufacturer, 'name');
        },

        manufacturerIsLoading() {
            return this.isLoading || this.manufacturer == null;
        },

        manufacturerRepository() {
            return this.repositoryFactory.create('product_manufacturer');
        },

        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        customFieldSetRepository() {
            return this.repositoryFactory.create('custom_field_set');
        },

        customFieldSetCriteria() {
            const criteria = new Criteria(1, null);
            criteria.addFilter(
                Criteria.equals('relations.entityName', 'product_manufacturer'),
            );

            return criteria;
        },

        mediaUploadTag() {
            return `sw-manufacturer-detail--${this.manufacturer.id}`;
        },

        tooltipSave() {
            if (this.acl.can('product_manufacturer.editor')) {
                const systemKey = this.$device.getSystemKey();

                return {
                    message: `${systemKey} + S`,
                    appearance: 'light',
                };
            }

            return {
                showDelay: 300,
                message: this.$tc('sw-privileges.tooltip.warning'),
                disabled: this.acl.can('order.editor'),
                showOnDisabledElements: true,
            };
        },

        tooltipCancel() {
            return {
                message: 'ESC',
                appearance: 'light',
            };
        },

        ...mapPropertyErrors('manufacturer', ['name']),
    },

    watch: {
        manufacturerId() {
            this.createdComponent();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            Shopware.ExtensionAPI.publishData({
                id: 'sw-manufacturer-detail__manufacturer',
                path: 'manufacturer',
                scope: this,
            });
            if (this.manufacturerId) {
                this.loadEntityData();
                return;
            }

            Shopware.State.commit('context/resetLanguageToDefault');
            this.manufacturer = this.manufacturerRepository.create();
        },

        async loadEntityData() {
            this.isLoading = true;

            const [manufacturerResponse, customFieldResponse] = await Promise.allSettled([
                this.manufacturerRepository.get(this.manufacturerId),
                this.customFieldSetRepository.search(this.customFieldSetCriteria),
            ]);

            if (manufacturerResponse.status === 'fulfilled') {
                this.manufacturer = manufacturerResponse.value;
            }

            if (customFieldResponse.status === 'fulfilled') {
                this.customFieldSets = customFieldResponse.value;
            }

            if (manufacturerResponse.status === 'rejected' || customFieldResponse.status === 'rejected') {
                this.createNotificationError({
                    message: this.$tc(
                        'global.notification.notificationLoadingDataErrorMessage',
                    ),
                });
            }

            this.isLoading = false;
        },

        abortOnLanguageChange() {
            return this.manufacturerRepository.hasChanges(this.manufacturer);
        },

        saveOnLanguageChange() {
            return this.onSave();
        },

        onChangeLanguage() {
            this.loadEntityData();
        },

        setMediaItem({ targetId }) {
            this.manufacturer.mediaId = targetId;
        },

        setMediaFromSidebar(media) {
            this.manufacturer.mediaId = media.id;
        },

        onUnlinkLogo() {
            this.manufacturer.mediaId = null;
        },

        openMediaSidebar() {
            this.$refs.mediaSidebarItem.openContent();
        },

        onDropMedia(dragData) {
            this.setMediaItem({ targetId: dragData.id });
        },

        onSave() {
            if (!this.acl.can('product_manufacturer.editor')) {
                return;
            }

            this.isLoading = true;

            this.manufacturerRepository.save(this.manufacturer).then(() => {
                this.isLoading = false;
                this.isSaveSuccessful = true;
                if (this.manufacturerId === null) {
                    this.$router.push({ name: 'sw.manufacturer.detail', params: { id: this.manufacturer.id } });
                    return;
                }

                this.loadEntityData();
            }).catch((exception) => {
                this.isLoading = false;
                this.createNotificationError({
                    message: this.$tc(
                        'global.notification.notificationSaveErrorMessageRequiredFieldsInvalid',
                    ),
                });
                throw exception;
            });
        },

        onCancel() {
            this.$router.push({ name: 'sw.manufacturer.index' });
        },
    },
};
