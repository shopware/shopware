import { Application, Component, State } from 'src/core/shopware';
import template from './sw-media-quickinfo-usage.html.twig';
import './sw-media-quickinfo-usage.scss';

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
            manufacturers: [],
            isLoading: false
        };
    },

    computed: {
        productStore() {
            return State.getStore('product');
        },

        moduleFactory() {
            return Application.getContainer('factory').module;
        },

        getUsages() {
            const usages = [];
            this.products.forEach((product) => {
                usages.push(this.getProductUsage(product));
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
            this.loadManufacturerAssociations();
        },

        loadProductAssociations() {
            this.products = [];
            this.item.getAssociation('productMedia').getList({
                page: 1,
                limit: 50
            }).then((response) => {
                this.products = response.items.map((productMedia) => {
                    return this.productStore.getById(productMedia.productId);
                });
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
                name: product.name,
                tooltip: this.$tc('sw-media.sidebar.usage.tooltipFoundInProducts'),
                link: {
                    name: 'sw.product.detail',
                    id: product.id
                },
                icon: this.getIconForModule('sw-product')
            };
        },

        getManufacturerUsage(manufacturer) {
            return {
                name: manufacturer.name,
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
