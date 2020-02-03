import template from './sw-settings-import-export-activity.html.twig';
import './sw-settings-import-export-activity.scss';

Shopware.Component.register('sw-settings-import-export-activity', {
    template,

    inject: ['repositoryFactory'],

    props: {
        // TODO: it needs an switch for import / export
    },

    data() {
        return {
            // TODO: change it to export/import activities
            products: null,
            isLoading: false
        };
    },

    computed: {
        // TODO: change to import/export activities
        productRepository() {
            return this.repositoryFactory.create('product');
        },

        activityCriteria() {
            const criteria = new Shopware.Data.Criteria();

            // TODO: here you can change the criteria for fetching the activites
            criteria.setPage(1);

            return criteria;
        },

        // TODO: change to activities columns
        exportActivityColumns() {
            return [
                {
                    property: 'name',
                    dataIndex: 'name',
                    label: 'Name', // TODO: change label to snippet path
                    // routerLink: 'sw.order.detail',
                    allowResize: true,
                    primary: true
                },
                {
                    property: 'id',
                    dataIndex: 'id',
                    label: 'Id', // TODO: change label to snippet path
                    allowResize: true,
                    primary: false
                },
                {
                    property: 'description',
                    dataIndex: 'description',
                    label: 'Description', // TODO: change label to snippet path
                    // label: '',
                    allowResize: true,
                    primary: false
                },
                {
                    property: 'stock',
                    dataIndex: 'stock',
                    label: 'Stock', // TODO: change label to snippet path
                    allowResize: true,
                    primary: false
                }
            ];
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.fetchActivities();
        },

        async fetchActivities() {
            this.isLoading = true;

            // TODO: change to activities
            this.products = await this.productRepository.search(this.activityCriteria, Shopware.Context.api)

            this.isLoading = false;
        }
    }
});
