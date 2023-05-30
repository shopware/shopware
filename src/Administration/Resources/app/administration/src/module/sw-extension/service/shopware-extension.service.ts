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
 * @private
 */
export default class ShopwareExtensionService {
    public readonly EXTENSION_VARIANT_TYPES: Readonly<EXTENSION_VARIANT_TYPES>;

    private readonly EXTENSION_TYPES: Readonly<EXTENSION_TYPES>;

    constructor(
        private readonly appModulesService: AppModulesService,
        private readonly extensionStoreActionService: ExtensionStoreActionService,
        private readonly discountCampaignService: ShopwareDiscountCampaignService,
        private readonly storeApiService: StoreApiService,
    ) {
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
        try {
            const { userInfo } = await this.storeApiService.checkLogin();
            Shopware.State.commit('shopwareExtensions/setUserInfo', userInfo);
        } catch {
            Shopware.State.commit('shopwareExtensions/setUserInfo', null);
        }
    }

    public orderVariantsByRecommendation(variants: ExtensionVariant[]): ExtensionVariant[] {
        const discounted = variants.filter((variant) => this.isVariantDiscounted(variant));
        const notDiscounted = variants.filter((variant) => !this.isVariantDiscounted(variant));

        return [
            ...this.orderByType(discounted),
            ...this.orderByType(notDiscounted),
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

    public async getOpenLink(extension: Extension): Promise<null|RawLocation> {
        if (extension.isTheme) {
            return this.getLinkToTheme(extension);
        }

        if (extension.type === this.EXTENSION_TYPES.APP) {
            return this.getLinkToApp(extension);
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

    private async updateModules() {
        const modules = await this.appModulesService.fetchAppModules();

        Shopware.State.commit('shopwareApps/setApps', modules);
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

    private getLinkToApp(extension: Extension) {
        const app = this.getAppFromStore(extension.name);

        if (!app) {
            return null;
        }

        if (this.appHasMainModule(app)) {
            return this.createLinkToModule(app.name);
        }

        return null;
    }

    private getAppFromStore(extensionName: string) {
        return Shopware.State.get('shopwareApps').apps.find((innerApp) => {
            return innerApp.name === extensionName;
        });
    }

    private appHasMainModule(app: AppModuleDefinition) {
        return !!app.mainModule?.source;
    }

    private createLinkToModule(appName: string) {
        return {
            name: 'sw.extension.module',
            params: {
                appName,
            },
        };
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
