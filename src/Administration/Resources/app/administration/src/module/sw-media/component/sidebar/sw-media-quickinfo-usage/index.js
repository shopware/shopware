import template from './sw-media-quickinfo-usage.html.twig';
import './sw-media-quickinfo-usage.scss';

const { Application, Component } = Shopware;

Component.register('sw-media-quickinfo-usage', {
    template,

    props: {
        item: {
            required: true,
            type: Object,
            validator(value) {
                return value.getEntityName() === 'media';
            }
        }
    },

    data() {
        return {
            products: [],
            categories: [],
            manufacturers: [],
            isLoading: false
        };
    },

    computed: {

        moduleFactory() {
            return Application.getContainer('factory').module;
        },

        getUsages() {
            const usages = [];
            this.products.forEach((product) => {
                usages.push(this.getProductUsage(product));
            });
            this.categories.forEach((category) => {
                usages.push(this.getCategoryUsage(category));
            });
            this.manufacturers.forEach((manufacturer) => {
                usages.push(this.getManufacturerUsage(manufacturer));
            });

            return usages;
        },

        isNotUsed() {
            return this.getUsages.length === 0;
        }
    },

    watch: {
        item() {
            this.createdComponent();
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.loadProductAssociations();
            this.loadCategoryAssociations();
            this.loadManufacturerAssociations();
        },

        loadProductAssociations() {
            this.products = this.item.productMedia;
        },

        loadCategoryAssociations() {
            this.categories = this.item.categories;
        },

        loadManufacturerAssociations() {
            this.manufacturers = this.item.productManufacturers;
        },

        getProductUsage(product) {
            return {
                name: product.translated.name,
                tooltip: this.$tc('sw-media.sidebar.usage.tooltipFoundInProducts'),
                link: {
                    name: 'sw.product.detail',
                    id: product.id
                },
                icon: this.getIconForModule('sw-product')
            };
        },

        getCategoryUsage(category) {
            return {
                name: category.translated.name,
                tooltip: this.$tc('sw-media.sidebar.usage.tooltipFoundInCategories'),
                link: {
                    name: 'sw.category.detail',
                    id: category.id
                },
                icon: this.getIconForModule('sw-category')
            };
        },

        getManufacturerUsage(manufacturer) {
            return {
                name: manufacturer.translated.name,
                tooltip: this.$tc('sw-media.sidebar.usage.tooltipFoundInManufacturers'),
                link: {
                    name: 'sw.manufacturer.detail',
                    id: manufacturer.id
                },
                icon: this.getIconForModule('sw-manufacturer')
            };
        },

        getIconForModule(name) {
            const module = this.moduleFactory.getModuleRegistry().get(name);
            return {
                name: module.manifest.icon,
                color: module.manifest.color
            };
        }
    }
});
