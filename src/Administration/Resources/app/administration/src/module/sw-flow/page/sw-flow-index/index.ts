import type Repository from '../../../../core/data/repository.data';
import type CriteriaType from '../../../../core/data/criteria.data';
import template from './sw-flow-index.html.twig';
import './sw-flow-index.scss';

const { Criteria } = Shopware.Data;

/**
 * @private
 * @sw-package after-sales
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default Shopware.Component.wrapComponentConfig({
    template,

    inject: [
        'acl',
        'repositoryFactory',
        'feature',
    ],

    data(): {
        isLoading: boolean;
        term: string;
        total: number;
        showUploadModal: boolean;
    } {
        return {
            isLoading: false,
            term: '',
            total: 0,
            showUploadModal: false,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        tabs(): Array<{ label: string; name: string; onClick: () => void }> {
            return [
                {
                    label: this.$t('sw-flow.general.tabMyFlows'),
                    name: 'sw.flow.index.flows',
                    onClick: () => {
                        void this.$router.push({ name: 'sw.flow.index.flows' });
                    },
                },
                {
                    label: this.$t('sw-flow.general.tabFlowTemplates'),
                    name: 'sw.flow.index.templates',
                    onClick: () => {
                        void this.$router.push({ name: 'sw.flow.index.templates' });
                    },
                },
            ];
        },

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
