/**
 * @sw-package framework
 */
const { Service } = Shopware;

enum USER_CONFIG_PERMISSIONS {
    READ = 'user_config:read',
    CREATE = 'user_config:create',
    UPDATE = 'user_config:update',
}

abstract class UserConfigClass {
    private currentUserId = this.getCurrentUserId();

    protected userConfig = this.createUserConfigEntity(this.getConfigurationKey());

    private aclService = Service('acl');

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

    protected async getUserConfig(): Promise<Entity<'user_config'>> {
        if (!this.aclService.can(USER_CONFIG_PERMISSIONS.READ)) {
            return this.userConfig;
        }

        const userConfig = Object.assign(this.createUserConfigEntity(this.getConfigurationKey()), this.userConfig, {
            value: (await Shopware.Service('userConfigService').search([this.getConfigurationKey()]))?.data?.[
                this.getConfigurationKey()
            ],
        });

        return this.handleEmptyUserConfig(userConfig);
    }

    protected async saveUserConfig(): Promise<void> {
        if (!this.aclService.can(USER_CONFIG_PERMISSIONS.CREATE) || !this.aclService.can(USER_CONFIG_PERMISSIONS.UPDATE)) {
            return;
        }

        this.setUserConfig();

        const configurationKey = this.getConfigurationKey();
        const upsertData: Record<string, unknown> = {};
        upsertData[configurationKey] = this.userConfig.value;

        await Shopware.Service('userConfigService').upsert(upsertData);
        await this.readUserConfig();
    }

    private createUserConfigEntity(configKey: string): Entity<'user_config'> {
        return {
            userId: this.currentUserId,
            key: configKey,
            value: [],
        } as Entity<'user_config'>;
    }

    private handleEmptyUserConfig(userConfig: Entity<'user_config'>): Entity<'user_config'> {
        if (!Array.isArray(userConfig?.value)) {
            userConfig.value = [];
        }

        return userConfig;
    }

    private getCurrentUserId(): EntityKey<'user'> {
        return Shopware.Store.get('session').currentUser?.id ?? ('' as EntityKey<'user'>);
    }
}

/**
 * @private
 */
export { UserConfigClass as default };
