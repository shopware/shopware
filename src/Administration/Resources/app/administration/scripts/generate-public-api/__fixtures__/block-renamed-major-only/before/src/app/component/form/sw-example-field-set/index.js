import template from './sw-example-field-set.html.twig';

const { Criteria } = Shopware.Data;

/**
 * @sw-package framework
 */
export default {
    template,

    inject: ['feature', 'repositoryFactory'],

    emits: ['tab-change'],

    props: {
        entityName: {
            type: String,
            required: true,
        },
    },

    data() {
        return {
            activeTab: null,
            fieldSets: [],
        };
    },

    computed: {
        customFieldSetRepository() {
            return this.repositoryFactory.create('custom_field_set');
        },

        tabItems() {
            return this.fieldSets.map((set) => ({
                label: set.config?.label ?? set.name,
                name: set.id,
            }));
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.equals('relations.entityName', this.entityName));

            this.fieldSets = await this.customFieldSetRepository.search(criteria);
            this.activeTab = this.fieldSets.first()?.id ?? null;
        },

        onChangeTab(tabName) {
            this.activeTab = tabName;
            this.$emit('tab-change', tabName);
        },
    },
};
