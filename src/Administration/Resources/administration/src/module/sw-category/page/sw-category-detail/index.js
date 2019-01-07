import { Component, Mixin, State } from 'src/core/shopware';
import { warn } from 'src/core/service/utils/debug.utils';
import template from './sw-category-detail.html.twig';
import './sw-category-detail.less';

Component.register('sw-category-detail', {
    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            category: null,
            isLoading: false
        };
    },

    computed: {
        categoryStore() {
            return State.getStore('category');
        }
    },

    watch: {
        '$route.params.id'() {
            this.createdComponent();
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.isLoading = true;

            if (this.$route.params.id) {
                this.categoryId = this.$route.params.id;
                this.category = this.categoryStore.getById(this.categoryId);
                this.isLoading = false;
                console.log(this.category);
            }
        },

        onSave() {
            return this.category.save().then(() => {
                this.createNotificationSuccess({
                    title: 'Success',
                    message: 'Success'
                });
            }).catch((exception) => {
                this.createNotificationError({
                    title: 'Failure',
                    message: exception
                });
                warn(this._name, exception.message, exception.response);
            });
        }
    }
});
