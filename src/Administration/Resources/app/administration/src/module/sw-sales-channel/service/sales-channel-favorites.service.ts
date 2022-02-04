import Vue from 'vue';

const { Application, Service, Context, State } = Shopware;
const { Criteria } = Shopware.Data;

enum USER_CONFIG_PERMISSIONS {
    READ = 'user_config:read',
    CREATE = 'user_config:create',
    UPDATE = 'user_config:update'
}

interface CurrentUserObject {
    id: string;
    [index: string]: unknown;
}

interface UserConfigObject {
    extensions: Record<string, unknown>;
    id: string;
    userId: string;
    key: string;
    value: string[];
    isNew: () => boolean;
}

interface AclServiceInterface {
    can(permission: string): boolean;
}

interface CriteriaInterface {
    addFilter(filter: unknown): CriteriaInterface;
    equals(field: string, value: string|number|boolean|null): unknown;
}

interface RepositorySearchResultInterface {
    total: number;
    first(): unknown;
}

interface RepositoryInterface {
    create(context: unknown): unknown;
    search(criteria: CriteriaInterface, context: unknown): Promise<RepositorySearchResultInterface>;
    save(entity: unknown, context: unknown): Promise<void>;
}

class SalesChannelFavoritesService {
    static USER_CONFIG_KEY = 'sales-channel-favorites';

    private userConfigRepository = <RepositoryInterface><unknown> Service('repositoryFactory').create('user_config');

    private currentUserId = this.getCurrentUserId();

    private userConfig = this.createUserConfigEntity();

    private state: { favorites: string[] } = Vue.observable({ favorites: [] });

    private aclService = <AclServiceInterface> Service('acl');

    constructor() {
        void this.initService();
    }

    private async initService(): Promise<void> {
        this.userConfig = await this.getUserConfig();

        this.state.favorites = this.userConfig.value;
    }

    public getFavoriteIds(): string[] {
        return this.state.favorites;
    }

    public isFavorite(salesChannelId: string): boolean {
        return this.state.favorites.includes(salesChannelId);
    }

    public update(state: boolean, salesChannelId: string): void {
        if (state && !this.isFavorite(salesChannelId)) {
            this.state.favorites.push(salesChannelId);
        } else if (!state && this.isFavorite(salesChannelId)) {
            const index = this.state.favorites.indexOf(salesChannelId);

            this.state.favorites.splice(index, 1);
        }

        void this.saveUserConfig();
    }

    public refresh(): void {
        this.userConfig = this.createUserConfigEntity();
        void this.initService();
    }

    private async getUserConfig(): Promise<UserConfigObject> {
        if (!this.aclService.can(USER_CONFIG_PERMISSIONS.READ)) {
            void Promise.resolve(this.userConfig);
        }

        const response = await this.userConfigRepository.search(this.getCriteria(), Context.api);
        const userConfig = <UserConfigObject> (response.total ? response.first() : this.userConfig);

        return this.handleEmptyUserConfig(userConfig);
    }

    private async saveUserConfig(): Promise<void> {
        if (!this.aclService.can(USER_CONFIG_PERMISSIONS.CREATE) || !this.aclService.can(USER_CONFIG_PERMISSIONS.UPDATE)) {
            void Promise.resolve();
        }

        this.userConfig.value = this.state.favorites;

        await this.userConfigRepository.save(this.userConfig, Context.api);
        await this.initService();
    }

    private createUserConfigEntity(): UserConfigObject {
        const entity = <UserConfigObject> this.userConfigRepository.create(Context.api);

        Object.assign(entity, {
            userId: this.currentUserId,
            key: SalesChannelFavoritesService.USER_CONFIG_KEY,
            value: [],
        });

        return entity;
    }

    private handleEmptyUserConfig(userConfig: UserConfigObject): UserConfigObject {
        if (!Array.isArray(userConfig.value)) {
            userConfig.value = [];
        }

        return userConfig;
    }

    private getCriteria(): CriteriaInterface {
        const criteria = <CriteriaInterface><unknown> new Criteria();

        criteria.addFilter(Criteria.equals('key', SalesChannelFavoritesService.USER_CONFIG_KEY));
        criteria.addFilter(Criteria.equals('userId', this.currentUserId));

        return criteria;
    }

    private getCurrentUserId(): string {
        return (State.get('session').currentUser as CurrentUserObject).id;
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

export { SalesChannelFavoritesService as default };
