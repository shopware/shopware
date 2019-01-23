import { Component, State, Mixin } from 'src/core/shopware';
import template from './sw-configuration-detail.html.twig';

Component.register('sw-configuration-detail', {
    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            group: {},
            groupId: null
        };
    },

    computed: {
        groupStore() {
            return State.getStore('configuration_group');
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

        onSave() {
            const titleSaveSuccess = this.$tc('sw-configuration.detail.titleSaveSuccess');
            const messageSaveSuccess = this.$tc('sw-configuration.detail.messageSaveSuccess', 0, { name: this.group.name });

            return this.group.save().then(() => {
                this.createNotificationSuccess({
                    title: titleSaveSuccess,
                    message: messageSaveSuccess
                });

                this.$refs.optionListing.setSorting();
                this.$refs.optionListing.getList();
            });
        }
    }
});
