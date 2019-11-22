import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-manufacturer-detail.html.twig';
import './sw-manufacturer-detail.scss';

const { Component, Mixin, StateDeprecated } = Shopware;
const { mapApiErrors } = Shopware.Component.getComponentHelper();

Component.register('sw-manufacturer-detail', {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('placeholder'),
        Mixin.getByName('notification'),
        Mixin.getByName('discard-detail-page-changes')('manufacturer')
    ],

    shortcuts: {
        'SYSTEMKEY+S': 'onSave',
        ESCAPE: 'onCancel'
    },

    props: {
        manufacturerId: {
            type: String,
            required: false,
            default: null
        }
    },


    data() {
        return {
            manufacturer: null,
            customFieldSets: [],
            isLoading: false,
            isSaveSuccessful: false
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier)
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

        languageStore() {
            return StateDeprecated.getStore('language');
        },

        mediaStore() {
            return StateDeprecated.getStore('media');
        },

        customFieldSetStore() {
            return StateDeprecated.getStore('custom_field_set');
        },

        mediaUploadTag() {
            return `sw-manufacturer-detail--${this.manufacturer.id}`;
        },

        tooltipSave() {
            const systemKey = this.$device.getSystemKey();

            return {
                message: `${systemKey} + S`,
                appearance: 'light'
            };
        },

        tooltipCancel() {
            return {
                message: 'ESC',
                appearance: 'light'
            };
        },

        ...mapApiErrors('manufacturer', ['name'])
    },

    watch: {
        manufacturerId() {
            this.createdComponent();
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (this.manufacturerId) {
                this.loadEntityData();
                return;
            }

            this.languageStore.setCurrentId(this.languageStore.systemLanguageId);
            this.manufacturer = this.manufacturerRepository.create(Shopware.Context.api);
        },

        loadEntityData() {
            this.isLoading = true;

            this.manufacturerRepository.get(this.manufacturerId, Shopware.Context.api).then((manufacturer) => {
                this.isLoading = false;
                this.manufacturer = manufacturer;
            });

            this.customFieldSetStore.getList({
                page: 1,
                limit: 100,
                criteria: CriteriaFactory.equals('relations.entityName', 'product_manufacturer'),
                associations: {
                    customFields: {
                        limit: 100,
                        sort: 'config.customFieldPosition'
                    }
                }
            }, true).then(({ items }) => {
                this.customFieldSets = items.filter(set => set.customFields.length > 0);
            });
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
            this.mediaStore.getByIdAsync(targetId);
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
            this.isLoading = true;

            this.manufacturerRepository.save(this.manufacturer, Shopware.Context.api).then(() => {
                this.isLoading = false;
                this.isSaveSuccessful = true;
                if (this.manufacturerId === null) {
                    this.$router.push({ name: 'sw.manufacturer.detail', params: { id: this.manufacturer.id } });
                    return;
                }

                this.loadEntityData();
            }).catch((exception) => {
                this.isLoading = false;
                const manufacturerName = this.manufacturer.name || this.manufacturer.translated.name;
                this.createNotificationError({
                    title: this.$tc('global.default.error'),
                    message: this.$tc(
                        'global.notification.notificationSaveErrorMessage', 0, { entityName: manufacturerName }
                    )
                });
                throw exception;
            });
        },

        onCancel() {
            this.$router.push({ name: 'sw.manufacturer.index' });
        }
    }
});
