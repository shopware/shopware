import { Component, State, Mixin } from 'src/core/shopware';
import template from './sw-property-detail.html.twig';

Component.register('sw-property-detail', {
    template,

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder')
    ],

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
            return State.getStore('property_group');
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.groupId = this.$route.params.id;
            this.group = this.groupStore.getById(this.groupId);
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        onSave() {
            const entityName = this.group.name || this.placeholder(this.group, 'name');

            const titleSaveError = this.$tc('global.notification.notificationSaveErrorTitle');
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
        }
    }
});
