import type { Entity } from '@shopware-ag/admin-extension-sdk/es/data/_internals/Entity';

const { Context, Data, Service, State } = Shopware;
const { Criteria } = Data;

enum USER_CONFIG_PERMISSIONS {
    READ = 'user_config:read',
    CREATE = 'user_config:create',
    UPDATE = 'user_config:update'
}

abstract class UserConfigClass {
    private userConfigRepository = Service('repositoryFactory').create('user_config');

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

        const response = await this.userConfigRepository.search(this.getCriteria(this.getConfigurationKey()), Context.api);

        const userConfig = response.first() || this.userConfig;

        return this.handleEmptyUserConfig(userConfig);
    }

    protected async saveUserConfig(): Promise<void> {
        if (!this.aclService.can(USER_CONFIG_PERMISSIONS.CREATE) || !this.aclService.can(USER_CONFIG_PERMISSIONS.UPDATE)) {
            return;
        }

        this.setUserConfig();

        await this.userConfigRepository.save(this.userConfig, Context.api);
        await this.readUserConfig();
    }

    private createUserConfigEntity(configKey: string): Entity<'user_config'> {
        const entity = this.userConfigRepository.create(Context.api);

        if (!entity) {
            throw new Error('Could not create user config entity');
        }

        Object.assign(entity, {
            userId: this.currentUserId,
            key: configKey,
            value: [],
        });

        return entity;
    }

    private handleEmptyUserConfig(userConfig: Entity<'user_config'>): Entity<'user_config'> {
        if (!Array.isArray(userConfig?.value)) {
            userConfig.value = [];
        }

        return userConfig;
    }

    private getCriteria(configKey : string): InstanceType<typeof Criteria> {
        const criteria = new Criteria(1, 25);

        criteria.addFilter(Criteria.equals('key', configKey));
        criteria.addFilter(Criteria.equals('userId', this.currentUserId));

        return criteria;
    }

    private getCurrentUserId(): string {
        return State.get('session').currentUser.id;
    }
}

/**
 * @private
 */
export { UserConfigClass as default };
