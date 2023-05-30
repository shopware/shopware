import Vue from 'vue';
import UserConfigClass from '../../../core/service/support/user-config.class';

const { Application } = Shopware;

class CmsElementFavoritesService extends UserConfigClass {
    static USER_CONFIG_KEY = 'cms-element-favorites';

    private state: { favorites: string[] } = Vue.observable({ favorites: [] });

    public getFavoriteElementNames(): string[] {
        return this.state.favorites;
    }

    public isFavorite(cmsElementName: string): boolean {
        return this.state.favorites.includes(cmsElementName);
    }

    public update(state: boolean, cmsElementName: string): void {
        if (state && !this.isFavorite(cmsElementName)) {
            this.state.favorites.push(cmsElementName);
        } else if (!state && this.isFavorite(cmsElementName)) {
            const index = this.state.favorites.indexOf(cmsElementName);

            this.state.favorites.splice(index, 1);
        }

        void this.saveUserConfig();
    }

    protected getConfigurationKey(): string {
        return CmsElementFavoritesService.USER_CONFIG_KEY;
    }

    protected async readUserConfig(): Promise<void> {
        this.userConfig = await this.getUserConfig();
        if (Array.isArray(this.userConfig.value)) {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
            this.state.favorites = this.userConfig.value;
        }
    }

    protected setUserConfig(): void {
        this.userConfig.value = this.state.favorites;
    }
}

let cmsElementFavoritesService: CmsElementFavoritesService;

Application.addServiceProvider('cmsElementFavorites', () => {
    if (!cmsElementFavoritesService) {
        cmsElementFavoritesService = new CmsElementFavoritesService();
    }

    return cmsElementFavoritesService;
});

/**
 * @private
 */
export { CmsElementFavoritesService as default };
