const { Context, Data, Service, State } = Shopware;
const { Criteria } = Data;

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

abstract class UserConfigClass {
    private userConfigRepository = <RepositoryInterface><unknown> Service('repositoryFactory').create('user_config');

    private currentUserId = this.getCurrentUserId();

    protected userConfig = this.createUserConfigEntity(this.getConfigurationKey());

    private aclService = <AclServiceInterface> Service('acl');

    constructor() {
        void this.readUserConfig();
    }

    /**
     * Copy user configuration into the service state.
     */
    protected abstract readUserConfig(): Promise<void>;

    /**
     * Copy the service state into the user configuration.
     */
    protected abstract setUserConfig(): void;

    /**
     * Returns the configuration key that is managed.
     */
    protected abstract getConfigurationKey(): string;

    public refresh(): void {
        this.userConfig = this.createUserConfigEntity(this.getConfigurationKey());
        void this.readUserConfig();
    }

    protected async getUserConfig(): Promise<UserConfigObject> {
        if (!this.aclService.can(USER_CONFIG_PERMISSIONS.READ)) {
            void Promise.resolve(this.userConfig);
        }

        const response = await this.userConfigRepository.search(this.getCriteria(this.getConfigurationKey()), Context.api);
        const userConfig = <UserConfigObject> (response.total ? response.first() : this.userConfig);

        return this.handleEmptyUserConfig(userConfig);
    }

    protected async saveUserConfig(): Promise<void> {
        if (!this.aclService.can(USER_CONFIG_PERMISSIONS.CREATE) || !this.aclService.can(USER_CONFIG_PERMISSIONS.UPDATE)) {
            void Promise.resolve();
        }

        this.setUserConfig();

        await this.userConfigRepository.save(this.userConfig, Context.api);
        await this.readUserConfig();
    }

    private createUserConfigEntity(configKey : string): UserConfigObject {
        const entity = <UserConfigObject> this.userConfigRepository.create(Context.api);

        Object.assign(entity, {
            userId: this.currentUserId,
            key: configKey,
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

    private getCriteria(configKey : string): CriteriaInterface {
        const criteria = <CriteriaInterface><unknown> new Criteria(1, 25);

        criteria.addFilter(Criteria.equals('key', configKey));
        criteria.addFilter(Criteria.equals('userId', this.currentUserId));

        return criteria;
    }

    private getCurrentUserId(): string {
        return (State.get('session').currentUser as CurrentUserObject).id;
    }
}

/**
 * @private
 */
export { UserConfigClass as default };
