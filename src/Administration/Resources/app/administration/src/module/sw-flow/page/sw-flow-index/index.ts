import type { MetaInfo } from 'vue-meta';
import type Repository from '../../../../core/data/repository.data';
import type CriteriaType from '../../../../core/data/criteria.data';
import template from './sw-flow-index.html.twig';
import './sw-flow-index.scss';

const { Criteria } = Shopware.Data;

/**
 * @private
 * @package business-ops
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default Shopware.Component.wrapComponentConfig({
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
            title: this.$createTitle(),
        };
    },

    computed: {
        flowRepository(): Repository<'flow'> {
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
            // eslint-disable-next-line max-len
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access,@typescript-eslint/no-unsafe-assignment
            const { total } = await this.flowRepository.searchIds(this.flowCriteria);
            // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
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
