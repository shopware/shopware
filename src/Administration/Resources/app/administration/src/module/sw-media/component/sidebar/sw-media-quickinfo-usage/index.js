import template from './sw-media-quickinfo-usage.html.twig';
import './sw-media-quickinfo-usage.scss';

const { Application, Component } = Shopware;
const types = Shopware.Utils.types;

Component.register('sw-media-quickinfo-usage', {
    template,

    inject: ['feature'],

    props: {
        item: {
            required: true,
            type: Object,
            validator(value) {
                return value.getEntityName() === 'media';
            },
        },
        routerLinkTarget: {
            required: false,
            type: String,
            default: '',
        },
    },

    data() {
        return {
            products: [],
            categories: [],
            manufacturers: [],
            mailTemplates: [],
            documentBaseConfigs: [],
            avatarUser: {},
            paymentMethods: [],
            shippingMethods: [],
            isLoading: false,
        };
    },

    computed: {

        moduleFactory() {
            return Application.getContainer('factory').module;
        },

        getUsages() {
            const usages = [];
            this.products.forEach(({ product }) => {
                usages.push(this.getProductUsage(product));
            });

            this.categories.forEach((category) => {
                usages.push(this.getCategoryUsage(category));
            });

            this.manufacturers.forEach((manufacturer) => {
                usages.push(this.getManufacturerUsage(manufacturer));
            });

            this.mailTemplates.forEach(({ mailTemplate }) => {
                if (!usages.some(usage => usage.link.id === mailTemplate.id)) {
                    usages.push(this.getMailTemplateUsage(mailTemplate));
                }
            });

            this.documentBaseConfigs.forEach((documentBaseConfig) => {
                usages.push(this.getDocumentBaseConfigUsage(documentBaseConfig));
            });

            this.paymentMethods.forEach((paymentMethod) => {
                usages.push(this.getPaymentMethodUsage(paymentMethod));
            });

            this.shippingMethods.forEach((shippingMethod) => {
                usages.push(this.getShippingMethodUsage(shippingMethod));
            });

            if (!types.isEmpty(this.avatarUser)) {
                usages.push(this.getAvatarUserUsage(this.avatarUser));
            }

            return usages;
        },

        isNotUsed() {
            return this.getUsages.length === 0;
        },
    },

    watch: {
        item() {
            this.createdComponent();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.loadProductAssociations();
            this.loadCategoryAssociations();
            this.loadManufacturerAssociations();
            this.loadMailTemplateAssociations();
            this.loadDocumentBaseConfigAssociations();
            this.loadAvatarUserAssociations();
            this.loadPaymentMethodAssociations();
            this.loadShippingMethodAssociations();
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

        loadMailTemplateAssociations() {
            this.mailTemplates = this.item.mailTemplateMedia;
        },

        loadDocumentBaseConfigAssociations() {
            this.documentBaseConfigs = this.item.documentBaseConfigs;
        },

        loadAvatarUserAssociations() {
            this.avatarUser = this.item.avatarUser;
        },

        loadPaymentMethodAssociations() {
            this.paymentMethods = this.item.paymentMethods;
        },

        loadShippingMethodAssociations() {
            this.shippingMethods = this.item.shippingMethods;
        },

        getProductUsage(product) {
            return {
                name: product.translated.name,
                tooltip: this.$tc('sw-media.sidebar.usage.tooltipFoundInProducts'),
                link: {
                    name: 'sw.product.detail',
                    id: product.id,
                },
                icon: this.getIconForModule('sw-product'),
            };
        },

        getCategoryUsage(category) {
            return {
                name: category.translated.name,
                tooltip: this.$tc('sw-media.sidebar.usage.tooltipFoundInCategories'),
                link: {
                    name: 'sw.category.detail',
                    id: category.id,
                },
                icon: this.getIconForModule('sw-category'),
            };
        },

        getManufacturerUsage(manufacturer) {
            return {
                name: manufacturer.translated.name,
                tooltip: this.$tc('sw-media.sidebar.usage.tooltipFoundInManufacturers'),
                link: {
                    name: 'sw.manufacturer.detail',
                    id: manufacturer.id,
                },
                icon: this.getIconForModule('sw-manufacturer'),
            };
        },

        getMailTemplateUsage(mailTemplate) {
            return {
                name: mailTemplate.translated.description,
                tooltip: this.$tc('sw-media.sidebar.usage.tooltipFoundInMailTemplate'),
                link: {
                    name: 'sw.mail.template.detail',
                    id: mailTemplate.id,
                },
                icon: this.getIconForModule('sw-mail-template'),
            };
        },

        getDocumentBaseConfigUsage(document) {
            return {
                name: document.name,
                tooltip: this.$tc('sw-media.sidebar.usage.tooltipFoundInDocument'),
                link: {
                    name: 'sw.settings.document.detail',
                    id: document.id,
                },
                icon: this.getIconForModule('sw-settings-document'),
            };
        },

        getAvatarUserUsage(user) {
            return {
                name: user.username,
                tooltip: this.$tc('sw-media.sidebar.usage.tooltipFoundInUser'),
                link: {
                    name: 'sw.settings.user.detail',
                    id: user.id,
                },
                icon: this.getIconForModule('sw-settings-user'),
            };
        },

        getPaymentMethodUsage(paymentMethod) {
            return {
                name: paymentMethod.translated.distinguishableName,
                tooltip: this.$tc('sw-media.sidebar.usage.tooltipFoundInPayment'),
                link: {
                    name: 'sw.settings.payment.detail',
                    id: paymentMethod.id,
                },
                icon: this.getIconForModule('sw-settings-payment'),
            };
        },

        getShippingMethodUsage(shippingMethod) {
            return {
                name: shippingMethod.translated.name,
                tooltip: this.$tc('sw-media.sidebar.usage.tooltipFoundShipping'),
                link: {
                    name: 'sw.settings.shipping.detail',
                    id: shippingMethod.id,
                },
                icon: this.getIconForModule('sw-settings-shipping'),
            };
        },

        getIconForModule(name) {
            const module = this.moduleFactory.getModuleRegistry().get(name);
            return {
                name: module.manifest.icon,
                color: module.manifest.color,
            };
        },
    },
});
