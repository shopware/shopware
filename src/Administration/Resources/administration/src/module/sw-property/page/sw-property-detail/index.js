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
            groupId: null
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

        onSave() {
            const titleSaveSuccess = this.$tc('sw-property.detail.titleSaveSuccess');
            const messageSaveSuccess = this.$tc('sw-property.detail.messageSaveSuccess', 0, { name: this.group.name });

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
