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
        Shopware() {
            return Shopware;
        },

        flowRepository(): Repository<'flow'> {
            return this.repositoryFactory.create('flow');
        },

        flowCriteria(): CriteriaType {
            return new Criteria(1, null);
        },

        defaultTabItem() {
            if (this.$route.name === 'sw.flow.index.templates') {
                return 'templates';
            }

            return 'flows';
        },

        tabItems() {
            return [
                {
                    label: this.$t('sw-flow.general.tabMyFlows'),
                    name: 'flows',
                    route: { name: 'sw.flow.index.flows' },
                    onClick: () => {
                        void this.$router.push({ name: 'sw.flow.index.flows' });
                    },
                },
                {
                    label: this.$t('sw-flow.general.tabFlowTemplates'),
                    name: 'templates',
                    route: { name: 'sw.flow.index.templates' },
                    onClick: () => {
                        void this.$router.push({ name: 'sw.flow.index.templates' });
                    },
                },
            ];
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
