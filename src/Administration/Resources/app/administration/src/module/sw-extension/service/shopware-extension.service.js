export default class ShopwareExtensionService {
    constructor(appModulesService, extensionStoreActionService, extensionStoreLicensesService, discountCampaignService) {
        this.appModuleService = appModulesService;
        this.extensionStoreActionService = extensionStoreActionService;
        this.extensionStoreLicensesService = extensionStoreLicensesService;
        this.discountCampaignService = discountCampaignService;

        this.EXTENSION_VARIANT_TYPES = Object.freeze({
            RENT: 'rent',
            BUY: 'buy',
            FREE: 'free'
        });

        this.EXTENSION_TYPES = Object.freeze({
            APP: 'app',
            PLUGIN: 'plugin'
        });
    }

    async purchaseExtension(extensionId, variantId, tocAccepted, permissionsAccepted) {
        await this.extensionStoreLicensesService.purchaseExtension(extensionId, variantId, tocAccepted, permissionsAccepted);

        await this.updateExtensionData();
    }

    async installExtension(extensionName, type) {
        await this.extensionStoreActionService.installExtension(extensionName, type);

        await this.updateExtensionData();
    }

    async updateExtension(extensionName, type) {
        await this.extensionStoreActionService.updateExtension(extensionName, type);

        await this.updateExtensionData();
    }

    async uninstallExtension(extensionName, type, removeData) {
        await this.extensionStoreActionService.uninstallExtension(extensionName, type, removeData);

        await this.updateExtensionData();
    }

    async removeExtension(extensionName, type) {
        await this.extensionStoreActionService.removeExtension(extensionName, type);

        await this.updateExtensionData();
    }

    async cancelAndRemoveExtension(licenseId) {
        await this.extensionStoreActionService.cancelAndRemoveExtension(licenseId);
    }

    async activateExtension(extensionId, type) {
        await this.extensionStoreActionService.activateExtension(extensionId, type);

        await this.updateModules();
    }

    async deactivateExtension(extensionId, type) {
        await this.extensionStoreActionService.deactivateExtension(extensionId, type);

        await this.updateModules();
    }

    async updateExtensionData() {
        await Shopware.State.dispatch('shopwareExtensions/updateLicensedExtensions');
        await Shopware.State.dispatch('shopwareExtensions/updateInstalledExtensions');
        await this.updateModules();
    }

    orderVariantsByRecommendation(variants) {
        const discounted = variants.filter((variant) => this.isVariantDiscounted(variant));
        const notDiscounted = variants.filter((variant) => !this.isVariantDiscounted(variant));

        return [
            ...this._orderByType(discounted),
            ...this._orderByType(notDiscounted)
        ];
    }

    isVariantDiscounted(variant) {
        if (!variant || !variant.discountCampaign
            || !variant.discountCampaign.discountedPrice
            || typeof variant.discountCampaign.discountedPrice !== 'number'
            || variant.discountCampaign.discountedPrice === variant.netPrice
        ) {
            return false;
        }

        return this.discountCampaignService.isDiscountCampaignActive(variant.discountCampaign);
    }

    getPriceFromVariant(variant) {
        if (this.isVariantDiscounted(variant)) {
            return variant.discountCampaign.discountedPrice;
        }

        return variant.netPrice;
    }

    mapVariantToRecommendation(variant) {
        switch (variant.type) {
            case this.EXTENSION_VARIANT_TYPES.FREE:
                return 0;
            case this.EXTENSION_VARIANT_TYPES.RENT:
                return 1;
            case this.EXTENSION_VARIANT_TYPES.BUY:
                return 2;
            default:
                return 3;
        }
    }

    canBeOpened(extension) {
        if (extension.isTheme) {
            return true;
        }

        if (extension.type === this.EXTENSION_TYPES.APP) {
            return !!this._getFirstAppModule(extension.name);
        }

        // TODO check for plugins
        return false;
    }

    getOpenLink(extension) {
        if (extension.isTheme) {
            return this._getLinkToTheme(extension);
        }

        if (extension.type === this.EXTENSION_TYPES.APP) {
            return this._getLinkToFirstModuleOfExtension(extension);
        }

        // TODO get link for plugins
        return null;
    }

    async updateModules() {
        const modules = await this.appModuleService.fetchAppModules();

        await Shopware.State.dispatch('shopwareApps/setAppModules', modules);
    }

    async _getLinkToTheme(extension) {
        const { Criteria } = Shopware.Data;
        const themeRepository = Shopware.Service('repositoryFactory').create('theme');

        const criteria = new Criteria(1, 1);
        criteria.addFilter(Criteria.equals('technicalName', extension.name));

        const { data: ids } = await themeRepository.searchIds(criteria, Shopware.Context.api);

        return {
            name: 'sw.theme.manager.detail',
            params: {
                id: ids[0]
            }
        };
    }

    _getLinkToFirstModuleOfExtension(extension) {
        const appModule = this._getFirstAppModule(extension.name);

        if (!appModule) {
            return null;
        }

        return {
            name: 'sw.my.apps.index',
            params: {
                appName: extension.name,
                moduleName: appModule.name
            }
        };
    }

    _getFirstAppModule(extensionName) {
        const app = Shopware.State.get('shopwareApps').apps.find((innerApp) => {
            return innerApp.name === extensionName;
        });

        if (!app || app.modules.length <= 0) {
            return null;
        }

        return app.modules[0];
    }

    _orderByType(variants) {
        const valueTypes = variants.map((variant, index) => {
            return { value: this.mapVariantToRecommendation(variant), index };
        });

        valueTypes.sort((first, second) => {
            return first.value - second.value;
        });

        return valueTypes.map((type) => {
            return variants[type.index];
        });
    }
}
