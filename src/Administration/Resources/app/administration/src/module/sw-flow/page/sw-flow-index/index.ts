import type Repository from 'src/core/data/repository.data';
import type { MetaInfo } from 'vue-meta';
import template from './sw-flow-index.html.twig';
import './sw-flow-index.scss';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

/**
 * @private
 * @package business-ops
 */
export default {
    template,

    inject: ['acl', 'repositoryFactory'],

    mixins: [
        Mixin.getByName('notification'),
    ],

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
    },

    created(): void {
        this.createComponent();
    },

    methods: {
        createComponent(): void {
            void this.getTotal();
        },

        async getTotal(): Promise<void> {
            const criteria = new Criteria(1, null);
            const { total } = await this.flowRepository.searchIds(criteria);
            this.total = total;
        },

        onUpdateTotalFlow(total: number): void {
            this.total = total;
        },

        onSearch(term: string): void {
            this.term = term;
        },
    },
};
