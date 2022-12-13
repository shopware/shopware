import type CriteriaType from 'src/core/data/criteria.data';
import type Repository from 'src/core/data/repository.data';
import type { MetaInfo } from 'vue-meta';
import template from './sw-flow-index.html.twig';
import './sw-flow-index.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-flow-index', {
    template,

    inject: ['acl', 'repositoryFactory'],

    data(): {
        isLoading: boolean,
        term: string,
        total: number,
        showUploadModal: boolean,
        } {
        return {
            isLoading: false,
            term: '',
            total: 0,
            showUploadModal: false,
        };
    },

    metaInfo(): MetaInfo {
        return {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call
            title: this.$createTitle() as string,
        };
    },

    computed: {
        flowRepository(): Repository {
            return this.repositoryFactory.create('flow');
        },

        flowCriteria(): CriteriaType {
            return new Criteria(1, null);
        },
    },

    created(): void {
        this.createComponent();
    },

    methods: {
        createComponent(): void {
            void this.getTotal();
        },

        async getTotal(): Promise<void> {
            const { total } = await this.flowRepository.searchIds(this.flowCriteria);
            this.total = total;
        },

        onUpdateTotalFlow(total: number): void {
            this.total = total;
        },

        onSearch(term: string): void {
            this.term = term;
        },
    },
});
