import type { RawLocation, Location } from 'vue-router';
import type { AppModulesService, AppModuleDefinition } from 'src/core/service/api/app-modules.service';
import type StoreApiService from 'src/core/service/api/store.api.service';
import type { ShopwareDiscountCampaignService } from 'src/app/service/discount-campaign.service';
import type {
    ExtensionStoreActionService,
    Extension,
    ExtensionVariant,
    ExtensionVariantType,
    ExtensionType,
} from './extension-store-action.service';

type EXTENSION_VARIANT_TYPES = {
    [Property in Uppercase<ExtensionVariantType>]: Lowercase<Property>
}

type EXTENSION_TYPES = {
    [Property in Uppercase<ExtensionType>]: Lowercase<Property>
}

interface LabeledLocation extends Location {
    label: string|null
}

/**
 * @package merchant-services
 * @deprecated tag:v6.5.0 - Will be private
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class ShopwareExtensionService {
    public readonly EXTENSION_VARIANT_TYPES: Readonly<EXTENSION_VARIANT_TYPES>;

    private readonly EXTENSION_TYPES: Readonly<EXTENSION_TYPES>;

    private readonly storeApiService: StoreApiService;

    /**
     * @deprecated tag:v6.5.0 - Parameter storeApiService will be required in future versions
     */
    constructor(
        private readonly appModulesService: AppModulesService,
        private readonly extensionStoreActionService: ExtensionStoreActionService,
        private readonly discountCampaignService: ShopwareDiscountCampaignService,
        storeApiService?: StoreApiService,
    ) {
        this.storeApiService = storeApiService ?? Shopware.Service('storeService');

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

    public async installExtension(extensionName: string, type: ExtensionType): Promise<void> {
        await this.extensionStoreActionService.installExtension(extensionName, type);

        await this.updateExtensionData();
    }

    public async updateExtension(extensionName: string, type: ExtensionType, allowNewPrivileges = false): Promise<void> {
        await this.extensionStoreActionService.updateExtension(extensionName, type, allowNewPrivileges);

        await this.updateExtensionData();
    }

    public async uninstallExtension(extensionName: string, type: ExtensionType, removeData: boolean): Promise<void> {
        await this.extensionStoreActionService.uninstallExtension(extensionName, type, removeData);

        await this.updateExtensionData();
    }

    public async removeExtension(extensionName: string, type: ExtensionType): Promise<void> {
        await this.extensionStoreActionService.removeExtension(extensionName, type);

        await this.updateExtensionData();
    }

    public async cancelLicense(licenseId: number): Promise<void> {
        await this.extensionStoreActionService.cancelLicense(licenseId);
    }

    public async activateExtension(extensionId: string, type: ExtensionType): Promise<void> {
        await this.extensionStoreActionService.activateExtension(extensionId, type);

        await this.updateModules();
    }

    public async deactivateExtension(extensionId: string, type: ExtensionType): Promise<void> {
        await this.extensionStoreActionService.deactivateExtension(extensionId, type);

        await this.updateModules();
    }

    public async updateExtensionData(): Promise<void> {
        Shopware.State.commit('shopwareExtensions/loadMyExtensions');

        try {
            await this.extensionStoreActionService.refresh();

            // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
            const myExtensions = await this.extensionStoreActionService.getMyExtensions();

            Shopware.State.commit('shopwareExtensions/myExtensions', myExtensions);

            await this.updateModules();
        } finally {
            Shopware.State.commit('shopwareExtensions/setLoading', false);
        }
    }

    public async checkLogin(): Promise<void> {
        if (Shopware.State.get('shopwareExtensions').userInfo === null) {
            Shopware.State.commit('shopwareExtensions/setLoginStatus', false);
        }

        try {
            const { userInfo } = await this.storeApiService.checkLogin();
            Shopware.State.commit('shopwareExtensions/setLoginStatus', !!userInfo);
            Shopware.State.commit('shopwareExtensions/setUserInfo', userInfo);
        } catch {
            Shopware.State.commit('shopwareExtensions/setLoginStatus', false);
            Shopware.State.commit('shopwareExtensions/setUserInfo', null);
        }
    }

    public orderVariantsByRecommendation(variants: ExtensionVariant[]): ExtensionVariant[] {
        const discounted = variants.filter((variant) => this.isVariantDiscounted(variant));
        const notDiscounted = variants.filter((variant) => !this.isVariantDiscounted(variant));

        return [
            ...this._orderByType(discounted),
            ...this._orderByType(notDiscounted),
        ];
    }

    public isVariantDiscounted(variant: ExtensionVariant): boolean {
        if (!variant || !variant.discountCampaign
            || typeof variant.discountCampaign.discountedPrice !== 'number'
            || variant.discountCampaign.discountedPrice === variant.netPrice
        ) {
            return false;
        }

        return this.discountCampaignService.isDiscountCampaignActive(variant.discountCampaign);
    }

    public getPriceFromVariant(variant: ExtensionVariant) {
        if (this.isVariantDiscounted(variant)) {
            // null assertion is fine here because we do all checks in isVariantDiscounted
            // eslint-disable-next-line @typescript-eslint/no-non-null-assertion
            return variant.discountCampaign!.discountedPrice!;
        }

        return variant.netPrice;
    }

    public mapVariantToRecommendation(variant: ExtensionVariant) {
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

    /**
     * @deprecated tag:v6.5.0 - will be removed without replacement. Use the return value of getOpenLink instead
     */
    public async canBeOpened(extension: Extension) {
        const openLink = await this.getOpenLink(extension);

        return !!openLink;
    }

    public async getOpenLink(extension: Extension): Promise<null|RawLocation> {
        if (extension.isTheme) {
            return this._getLinkToTheme(extension);
        }

        if (extension.type === this.EXTENSION_TYPES.APP) {
            return this._getLinkToApp(extension);
        }

        // Only show open link when extension is active. The route is maybe not available
        if (!extension.active) {
            return null;
        }

        /* eslint-disable @typescript-eslint/no-unsafe-member-access,@typescript-eslint/no-unsafe-assignment */
        const entryRoutes = Shopware.State.get('extensionEntryRoutes').routes;

        if (entryRoutes[extension.name] !== undefined) {
            return {
                name: entryRoutes[extension.name].route,
                label: entryRoutes[extension.name].label ?? null,
            } as LabeledLocation;
        }
        /* eslint-enable */

        return null;
    }

    /**
     * @deprecated tag:v6.5.0 - will be private in future versions
     */
    public async updateModules() {
        const modules = await this.appModulesService.fetchAppModules();

        Shopware.State.commit('shopwareApps/setApps', modules);
    }

    /**
     * @deprecated tag:v6.5.0 - will be removed. Use private function getLinkToTheme instead
     */
    public async _getLinkToTheme(extension: Extension) {
        return this.getLinkToTheme(extension);
    }

    private async getLinkToTheme(extension: Extension) {
        const { Criteria } = Shopware.Data;
        const themeRepository = Shopware.Service('repositoryFactory').create('theme');

        const criteria = new Criteria(1, 1);
        criteria.addFilter(Criteria.equals('technicalName', extension.name));

        const { data: ids } = await themeRepository.searchIds(criteria);

        if (ids.length === 0) {
            return null;
        }

        return {
            name: 'sw.theme.manager.detail',
            params: {
                id: ids[0],
            },
        };
    }

    /**
     * @deprecated tag:v6.5.0 - will be removed. Use private function getLinkToApp instead
     */
    public _getLinkToApp(extension: Extension) {
        return this.getLinkToApp(extension);
    }

    private getLinkToApp(extension: Extension) {
        const app = this._getAppFromStore(extension.name);

        if (!app) {
            return null;
        }

        if (this._appHasMainModule(app)) {
            return this._createLinkToModule(app.name);
        }

        return null;
    }

    /**
     * @deprecated tag:v6.5.0 - will be removed. Use private function getAppFromStore instead
     */
    public _getAppFromStore(extensionName: string) {
        return this.getAppFromStore(extensionName);
    }

    private getAppFromStore(extensionName: string) {
        return Shopware.State.get('shopwareApps').apps.find((innerApp) => {
            return innerApp.name === extensionName;
        });
    }

    /**
     * @deprecated tag:v6.5.0 - will be removed. Use private function appHasMainModule instead
     */
    public _appHasMainModule(app: AppModuleDefinition) {
        return this.appHasMainModule(app);
    }

    private appHasMainModule(app: AppModuleDefinition) {
        return !!app.mainModule?.source;
    }

    /**
     * @deprecated tag:v6.5.0 - will be removed. Use private function createLinkToModule instead
     */
    public _createLinkToModule(appName: string) {
        return this.createLinkToModule(appName);
    }

    private createLinkToModule(appName: string) {
        return {
            name: 'sw.my.apps.index',
            params: {
                appName,
            },
        };
    }

    /**
     * @deprecated tag:v6.5.0 - will be removed. use private function orderByType instead.
     */
    public _orderByType(variants: ExtensionVariant[]) {
        return this.orderByType(variants);
    }

    private orderByType(variants: ExtensionVariant[]) {
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
