import template from './sw-media-quickinfo-usage.html.twig';
import './sw-media-quickinfo-usage.scss';

const { Application } = Shopware;
const types = Shopware.Utils.types;
const { Criteria } = Shopware.Data;

/**
 * @package buyers-experience
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
    ],

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
            avatarUsers: [],
            paymentMethods: [],
            shippingMethods: [],
            layouts: [],
            landingPages: [],
            isLoading: false,
        };
    },

    computed: {

        productRepository() {
            return this.repositoryFactory.create('product');
        },

        landingPageRepository() {
            return this.repositoryFactory.create('landing_page');
        },

        categoryRepository() {
            return this.repositoryFactory.create('category');
        },

        cmsPageRepository() {
            return this.repositoryFactory.create('cms_page');
        },

        moduleFactory() {
            return Application.getContainer('factory').module;
        },

        slotConfigCriteria() {
            const criteria = new Criteria();

            criteria.setLimit(5);
            criteria.addFilter(Criteria.contains('slotConfig.*.media.value', this.item.id));
            criteria.addFields('name');

            return criteria;
        },

        cmsPageBlockConfigCriteria() {
            const criteria = new Criteria();

            criteria.setLimit(5);
            criteria.addFilter(Criteria.equals('sections.blocks.slots.config.media.value', this.item.id));
            criteria.addFields('name');

            return criteria;
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

            this.layouts.forEach((layout) => {
                usages.push(this.getLayoutUsage(layout));
            });

            if (!types.isEmpty(this.avatarUsers)) {
                this.avatarUsers.forEach((avatarUser) => {
                    usages.push(this.getAvatarUserUsage(avatarUser));
                });
            }

            this.landingPages.forEach((landingPage) => {
                usages.push(this.getLandingPageUsage(landingPage));
            });

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
        async createdComponent() {
            this.loadProductAssociations();
            this.loadCategoryAssociations();
            this.loadManufacturerAssociations();
            this.loadMailTemplateAssociations();
            this.loadDocumentBaseConfigAssociations();
            this.loadAvatarUserAssociations();
            this.loadPaymentMethodAssociations();
            this.loadShippingMethodAssociations();
            this.loadLayoutAssociations();
            await this.loadSlotConfigAssociations();
        },

        async loadSlotConfigAssociations() {
            this.isLoading = true;

            const [foundInProducts, foundInLandingPages, foundInCategories, foundInCmsPages] = await Promise.all([
                this.productRepository.search(this.slotConfigCriteria),
                this.landingPageRepository.search(this.slotConfigCriteria),
                this.categoryRepository.search(this.slotConfigCriteria),
                this.cmsPageRepository.search(this.cmsPageBlockConfigCriteria),
            ]);

            foundInProducts.forEach((foundInProduct) => {
                if (this.products && !this.products.some((productMedia) => productMedia.product.id === foundInProduct.id)) {
                    this.products.push({ product: foundInProduct });
                }
            });

            this.landingPages = foundInLandingPages;

            foundInCategories.forEach((foundInCategory) => {
                if (this.categories && !this.categories.some((category) => category.id === foundInCategory.id)) {
                    this.categories.push(foundInCategory);
                }
            });

            foundInCmsPages.forEach((foundInCmsPage) => {
                if (this.layouts && !this.layouts.some((layout) => layout.id === foundInCmsPage.id)) {
                    this.layouts.push(foundInCmsPage);
                }
            });

            this.isLoading = false;
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
            this.avatarUsers = this.item.avatarUsers;
        },

        loadPaymentMethodAssociations() {
            this.paymentMethods = this.item.paymentMethods;
        },

        loadShippingMethodAssociations() {
            this.shippingMethods = this.item.shippingMethods;
        },

        loadLayoutAssociations() {
            this.layouts = [];
            this.item.cmsBlocks.forEach((layout) => {
                if (!this.isExistedCmsMedia(layout.section.pageId)) {
                    this.layouts.push({
                        id: layout.section.pageId,
                        name: layout.section.page.translated.name,
                    });
                }
            });

            this.item.cmsSections.forEach((layout) => {
                if (!this.isExistedCmsMedia(layout.pageId)) {
                    this.layouts.push({
                        id: layout.pageId,
                        name: layout.page.translated.name,
                    });
                }
            });

            this.item.cmsPages.forEach((layout) => {
                if (!this.isExistedCmsMedia(layout.id)) {
                    this.layouts.push({
                        id: layout.id,
                        name: layout.translated.name,
                    });
                }
            });
        },

        isExistedCmsMedia(id) {
            return this.layouts.some(layout => {
                return layout.id === id;
            });
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

        getLayoutUsage(layout) {
            return {
                name: layout.name,
                tooltip: this.$tc('sw-media.sidebar.usage.tooltipFoundLayout'),
                link: {
                    name: 'sw.cms.detail',
                    id: layout.id,
                },
                icon: this.getIconForModule('sw-cms'),
            };
        },

        getLandingPageUsage(landingPage) {
            return {
                name: landingPage.translated.name,
                tooltip: this.$tc('sw-media.sidebar.usage.tooltipFoundInLandingPages'),
                link: {
                    name: 'sw.category.landingPageDetail',
                    id: landingPage.id,
                },
                icon: this.getIconForModule('sw-category'),
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
};
