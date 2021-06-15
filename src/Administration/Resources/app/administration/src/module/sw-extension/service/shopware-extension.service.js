export default class ShopwareExtensionService {
    constructor(appModulesService, extensionStoreActionService, discountCampaignService) {
        this.appModuleService = appModulesService;
        this.extensionStoreActionService = extensionStoreActionService;
        this.discountCampaignService = discountCampaignService;

        this.EXTENSION_VARIANT_TYPES = Object.freeze({
            RENT: 'rent',
            BUY: 'buy',
            FREE: 'free',
        });

        this.EXTENSION_TYPES = Object.freeze({
            APP: 'app',
            PLUGIN: 'plugin',
        });
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

    async cancelLicense(licenseId) {
        await this.extensionStoreActionService.cancelLicense(licenseId);
    }

    async activateExtension(extensionId, type) {
        await this.extensionStoreActionService.activateExtension(extensionId, type);

        await this.updateModules();
    }

    async deactivateExtension(extensionId, type) {
        await this.extensionStoreActionService.deactivateExtension(extensionId, type);

        await this.updateModules();
    }

    updateExtensionData() {
        Shopware.State.commit('shopwareExtensions/loadMyExtensions');

        const extensionStoreActionService = Shopware.Service('extensionStoreActionService');

        return extensionStoreActionService.refresh()
            .then(() => {
                return extensionStoreActionService.getMyExtensions(
                    { ...Shopware.Context.api, languageId: Shopware.State.get('session').languageId },
                );
            }).then((myExtensions) => {
                Shopware.State.commit('shopwareExtensions/myExtensions', myExtensions);

                return this.updateModules();
            }).catch(e => {
                return Promise.reject(e);
            })
            .finally(() => {
                Shopware.State.commit('shopwareExtensions/setLoading', false);
            });
    }

    checkLogin() {
        if (!Shopware.State.get('shopwareExtensions').shopwareId) {
            Shopware.State.commit('shopwareExtensions/setLoginStatus', false);
        }

        return Shopware.Service('storeService').checkLogin().then((response) => {
            Shopware.State.commit('shopwareExtensions/setLoginStatus', response.storeTokenExists);
        }).catch(() => {
            Shopware.State.commit('shopwareExtensions/setLoginStatus', false);
        });
    }

    orderVariantsByRecommendation(variants) {
        const discounted = variants.filter((variant) => this.isVariantDiscounted(variant));
        const notDiscounted = variants.filter((variant) => !this.isVariantDiscounted(variant));

        return [
            ...this._orderByType(discounted),
            ...this._orderByType(notDiscounted),
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
        return this.getOpenLink(extension).then(res => {
            return !!res;
        });
    }

    async getOpenLink(extension) {
        if (extension.isTheme) {
            // eslint-disable-next-line no-return-await
            return await this._getLinkToTheme(extension);
        }

        if (extension.type === this.EXTENSION_TYPES.APP) {
            return this._getLinkToApp(extension);
        }

        // Only show open link when extension is active. The route is maybe not available
        if (!extension.active) {
            return null;
        }

        const entryRoutes = Shopware.State.get('extensionEntryRoutes').routes;

        if (entryRoutes[extension.name] !== undefined) {
            return {
                name: entryRoutes[extension.name].route,
                label: entryRoutes[extension.name].label || null,
            };
        }

        return null;
    }

    async updateModules() {
        const modules = await this.appModuleService.fetchAppModules();

        Shopware.State.commit('shopwareApps/setApps', modules);
    }

    async _getLinkToTheme(extension) {
        const { Criteria } = Shopware.Data;
        const themeRepository = Shopware.Service('repositoryFactory').create('theme');

        const criteria = new Criteria(1, 1);
        criteria.addFilter(Criteria.equals('technicalName', extension.name));

        const { data: ids } = await themeRepository.searchIds(criteria);
        const hasIds = ids.length > 0;

        if (!hasIds) {
            return null;
        }

        return {
            name: 'sw.theme.manager.detail',
            params: {
                id: ids[0],
            },
        };
    }

    _getLinkToApp(extension) {
        const app = this._getAppFromStore(extension.name);

        if (!app) {
            return null;
        }

        if (this._appHasMainModule(app)) {
            return this._createLinkToModule(app.name);
        }

        return null;
    }

    _getAppFromStore(extensionName) {
        return Shopware.State.get('shopwareApps').apps.find((innerApp) => {
            return innerApp.name === extensionName;
        });
    }

    _appHasMainModule(app) {
        return !!app.mainModule && !!app.mainModule.source;
    }

    _createLinkToModule(appName) {
        return {
            name: 'sw.my.apps.index',
            params: {
                appName,
            },
        };
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
