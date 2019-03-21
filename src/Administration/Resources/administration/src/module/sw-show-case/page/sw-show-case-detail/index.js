import { Component } from 'src/core/shopware';
import template from './sw-show-case-detail.html.twig';

Component.register('sw-show-case-detail', {
    template,
    inject: ['repositoryFactory', 'context'],

    data() {
        return {
            record: null
        };
    },

    created() {
        this.createdComponent();
    },

    watch: {
        '$route.params.id'() {
            this.createdComponent();
        }
    },

    methods: {
        createdComponent() {
            this.entityId = this.$route.params.id;

            this.productRepository = this.repositoryFactory.create('product', '/product');

            return this.productRepository.createVersion(this.entityId, this.context)
                .then((versionContext) => {
                    this.versionContext = versionContext;
                })
                .then(() => {
                    return this.productRepository.get(this.entityId, this.versionContext);
                })
                .then((entity) => {
                    this.record = entity;
                    return entity;
                });
        },

        save() {
            return this.productRepository.save(this.record, this.versionContext).then(() => {
                return this.productRepository.mergeVersion(this.versionContext.versionId, this.versionContext);
            });
        }
    }
});
