import Vue from 'vue';
import UserConfigClass from '../../../core/service/support/user-config.class';

const { Application } = Shopware;

class CmsBlockFavoritesService extends UserConfigClass {
    static USER_CONFIG_KEY = 'cms-block-favorites';

    private state: { favorites: string[] } = Vue.observable({ favorites: [] });

    public getFavoriteBlockNames(): string[] {
        return this.state.favorites;
    }

    public isFavorite(cmsBlockName: string): boolean {
        return this.state.favorites.includes(cmsBlockName);
    }

    public update(state: boolean, cmsBlockName: string): void {
        if (state && !this.isFavorite(cmsBlockName)) {
            this.state.favorites.push(cmsBlockName);
        } else if (!state && this.isFavorite(cmsBlockName)) {
            const index = this.state.favorites.indexOf(cmsBlockName);

            this.state.favorites.splice(index, 1);
        }

        void this.saveUserConfig();
    }

    protected getConfigurationKey(): string {
        return CmsBlockFavoritesService.USER_CONFIG_KEY;
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

let cmsBlockFavoritesService: CmsBlockFavoritesService;

Application.addServiceProvider('cmsBlockFavorites', () => {
    if (!cmsBlockFavoritesService) {
        cmsBlockFavoritesService = new CmsBlockFavoritesService();
    }

    return cmsBlockFavoritesService;
});

/**
 * @private
 */
export { CmsBlockFavoritesService as default };
