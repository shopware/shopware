import type Repository from '../../../../core/data/repository.data';
import type CriteriaType from '../../../../core/data/criteria.data';
import type { TabItem } from '@shopware-ag/meteor-component-library/dist/esm/MtTabs';
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
        flowRepository(): Repository<'flow'> {
            return this.repositoryFactory.create('flow');
        },

        flowCriteria(): CriteriaType {
            return new Criteria(1, null);
        },

        useMeteorTabs(): boolean {
            return Shopware.Feature.isActive('V6_8_0_0');
        },

        activeTab(): string {
            return this.$route.name ?? 'sw.flow.index.flows';
        },

        tabItems(): TabItem[] {
            return [
                this.createTabItem('sw-flow.general.tabMyFlows', { name: 'sw.flow.index.flows' }),
                this.createTabItem('sw-flow.general.tabFlowTemplates', { name: 'sw.flow.index.templates' }),
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

        createTabItem(label: string, route: { name: string }): TabItem {
            return {
                label: this.$t(label),
                name: route.name,
                onClick: () => {
                    void this.$router.push(route);
                },
            };
        },
    },
});
