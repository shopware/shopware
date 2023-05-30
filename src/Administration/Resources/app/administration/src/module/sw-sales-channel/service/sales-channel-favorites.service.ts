/**
 * @package sales-channel
 */

import Vue from 'vue';
import UserConfigClass from '../../../core/service/support/user-config.class';

const { Application } = Shopware;

class SalesChannelFavoritesService extends UserConfigClass {
    static USER_CONFIG_KEY = 'sales-channel-favorites';

    private state: { favorites: string[] } = Vue.observable({ favorites: [] });

    private async initService(): Promise<void> {
        this.userConfig = await this.getUserConfig();

        // @ts-expect-error - this object contains value
        if (this.userConfig?.value?.length) {
            this.state.favorites = this.userConfig.value as string[];
        }
    }

    public getFavoriteIds(): string[] {
        return this.state.favorites;
    }

    public isFavorite(salesChannelId: string): boolean {
        return this.state.favorites.includes(salesChannelId);
    }

    public update(state: boolean, salesChannelId: string): Promise<void> {
        if (state && !this.isFavorite(salesChannelId)) {
            this.state.favorites.push(salesChannelId);
        } else if (!state && this.isFavorite(salesChannelId)) {
            const index = this.state.favorites.indexOf(salesChannelId);

            this.state.favorites.splice(index, 1);
        }

        return this.saveUserConfig();
    }

    protected getConfigurationKey(): string {
        return SalesChannelFavoritesService.USER_CONFIG_KEY;
    }

    protected async readUserConfig(): Promise<void> {
        this.userConfig = await this.getUserConfig();
        if (Array.isArray(this.userConfig?.value)) {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
            this.state.favorites = this.userConfig.value;
        }
    }

    protected setUserConfig(): void {
        this.userConfig.value = this.state.favorites;
    }
}

let salesChannelFavoritesService: SalesChannelFavoritesService;

// @ts-expect-error
Application.addServiceProvider('salesChannelFavorites', () => {
    if (!salesChannelFavoritesService) {
        salesChannelFavoritesService = new SalesChannelFavoritesService();
    }

    return salesChannelFavoritesService;
});

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export { SalesChannelFavoritesService as default };
