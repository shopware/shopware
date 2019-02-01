import { Component, State } from 'src/core/shopware';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-navigation-tree.html.twig';
import './sw-navigation-tree.scss';

Component.register('sw-navigation-tree', {
    template,

    data() {
        return {
            navigations: [],
            activeNavigationId: null
        };
    },

    computed: {
        navigationStore() {
            return State.getStore('navigation');
        }
    },

    watch: {
        '$route.params.id'() {
            this.getActiveNavigation();
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.loadNavigation(null);
        },

        loadNavigation(parentId) {
            return this.navigationStore.getList({
                page: 1,
                limit: 500,
                criteria: CriteriaFactory.equals('navigation.parentId', parentId)
            }).then((response) => {
                this.isLoading = false;
                this.navigations = Object.values(this.navigationStore.store);
                return response.items;
            });
        },

        getActiveNavigation() {
            this.activeNavigationId = this.$route.params.id;
        },

        onUpdatePositions() {
            this.$emit('sw-navigation-on-save');
        },

        onAddSubNavigation(parent) {
            const item = this.navigationStore.create();
            item.setLocalData({
                parentId: parent.id,
                name: 'Neue Navigationspunkt'
            });
            item.save().then(() => {
                this.loadNavigation(parent.id);
            });
        },

        onDeleteNavigation(item) {
            const navigation = this.navigationStore.getById(item.id);

            navigation.delete(true).then(() => {
                this.$emit('sw-navigation-on-refresh');
                if (item.id === this.activeNavigationId) {
                    this.$emit('sw-navigation-on-reset-details');
                }
            });
        }
    }
});
