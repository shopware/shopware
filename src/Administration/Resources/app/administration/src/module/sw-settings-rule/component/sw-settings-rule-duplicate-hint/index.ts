import type CriteriaType from 'src/core/data/criteria.data';
import template from './sw-settings-rule-duplicate-hint.html.twig';

const { Criteria } = Shopware.Data;

type RuleEntity = Entity<'rule'>;

/**
 * @private
 * @sw-package fundamentals@after-sales
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    inject: ['repositoryFactory'],

    props: {
        rule: {
            type: Object as PropType<RuleEntity>,
            required: true,
        },
    },

    data(): { duplicates: RuleEntity[]; duplicatesTotal: number } {
        return {
            duplicates: [],
            duplicatesTotal: 0,
        };
    },

    computed: {
        ruleRepository() {
            return this.repositoryFactory.create('rule');
        },

        duplicateSearchKey(): string {
            return `${this.rule.id}:${this.rule.configHash ?? ''}`;
        },

        moreDuplicatesCount(): number {
            return Math.max(this.duplicatesTotal - this.duplicates.length, 0);
        },

        duplicateCriteria(): CriteriaType {
            const criteria = new Criteria(1, 10);
            criteria.addFilter(Criteria.equals('configHash', this.rule.configHash ?? null));
            criteria.addFilter(Criteria.not('AND', [Criteria.equals('id', this.rule.id)]));
            criteria.addSorting(Criteria.sort('name', 'ASC'));

            return criteria;
        },
    },

    watch: {
        duplicateSearchKey: {
            immediate: true,
            handler(): void {
                void this.loadDuplicates();
            },
        },
    },

    methods: {
        async loadDuplicates(): Promise<void> {
            if (!this.rule.configHash) {
                this.duplicates = [];
                this.duplicatesTotal = 0;

                return;
            }

            const searchKey = this.duplicateSearchKey;
            const result = await this.ruleRepository.search(this.duplicateCriteria);

            if (searchKey !== this.duplicateSearchKey) {
                return;
            }

            this.duplicates = Array.from(result);
            this.duplicatesTotal = result.total ?? this.duplicates.length;
        },

        getRuleLink(rule: RuleEntity): string {
            return this.$router.resolve({ name: 'sw.settings.rule.detail.base', params: { id: rule.id } }).href;
        },
    },
});
