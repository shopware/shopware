import template from './sw-media-quickinfo-usage.html.twig';
import './sw-media-quickinfo-usage.scss';

const { Application, Component, StateDeprecated } = Shopware;

Component.register('sw-media-quickinfo-usage', {
    template,

    props: {
        item: {
            required: true,
            type: Object,
            validator(value) {
                return value.type === 'media';
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
        productStore() {
            return StateDeprecated.getStore('product');
        },

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

    created() {
        this.createdComponent();
    },

    watch: {
        item() {
            this.createdComponent();
        }
    },

    methods: {
        createdComponent() {
            this.isLoading = true;
            this.loadProductAssociations();
            this.loadCategoryAssociations();
            this.loadManufacturerAssociations();
        },

        loadProductAssociations() {
            this.products = [];
            this.item.getAssociation('productMedia').getList({
                page: 1,
                limit: 50,
                associations: {
                    product: {}
                }
            }).then((response) => {
                this.products = response.items.map((productMedia) => {
                    return productMedia.product;
                });
                this.isLoading = false;
            });
        },

        loadCategoryAssociations() {
            this.item.getAssociation('categories').getList({
                page: 1,
                limit: 50
            }).then((response) => {
                this.categories = response.items;
                this.isLoading = false;
            });
        },

        loadManufacturerAssociations() {
            this.item.getAssociation('productManufacturers').getList({
                page: 1,
                limit: 50
            }).then((response) => {
                this.manufacturers = response.items;
                this.isLoading = false;
            });
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
