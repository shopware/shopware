import template from './sw-property-detail.html.twig';

const { Component, StateDeprecated, Mixin } = Shopware;

Component.register('sw-property-detail', {
    template,

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder')
    ],

    shortcuts: {
        'SYSTEMKEY+S': 'onSave',
        ESCAPE: 'onCancel'
    },

    data() {
        return {
            group: {},
            groupId: null,
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
            return this.placeholder(this.group, 'name');
        },

        groupStore() {
            return StateDeprecated.getStore('property_group');
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
        }

    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.groupId = this.$route.params.id;
            this.loadEntityData();
        },

        loadEntityData() {
            this.group = this.groupStore.getById(this.groupId);

            if (this.$refs.optionListing) {
                this.$refs.optionListing.setSorting();
                this.$refs.optionListing.getList();
            }
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        saveOnLanguageChange() {
            return this.onSave();
        },

        abortOnLanguageChange() {
            return this.group.hasChanges();
        },

        onChangeLanguage() {
            this.loadEntityData();
        },

        onSave() {
            const entityName = this.group.name || this.placeholder(this.group, 'name');

            const titleSaveError = this.$tc('global.default.error');
            const messageSaveError = this.$tc(
                'global.notification.notificationSaveErrorMessage', 0, { entityName: entityName }
            );

            this.isSaveSuccessful = false;
            this.isLoading = true;

            return this.group.save().then(() => {
                this.isLoading = false;
                this.isSaveSuccessful = true;

                this.$refs.optionListing.setSorting();
                this.$refs.optionListing.getList();
            }).catch((exception) => {
                this.createNotificationError({
                    title: titleSaveError,
                    message: messageSaveError
                });
                this.isLoading = false;
                throw exception;
            });
        },

        onCancel() {
            this.$router.push({ name: 'sw.property.index' });
        }
    }
});
