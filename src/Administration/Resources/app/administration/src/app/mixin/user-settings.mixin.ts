/* eslint-disable @typescript-eslint/prefer-promise-reject-errors */
/**
 * @sw-package framework
 */

/* @private */
import { defineComponent } from 'vue';

interface UserSettingsEntity {
    id?: string;
    key?: string;
    userId?: string | null;
    value?: unknown;
    [key: string]: unknown;
}

interface UserConfigRepository {
    search(criteria: unknown, context: unknown): Promise<UserSettingsEntity[]>;
    create(context: unknown): UserSettingsEntity;
    save(entity: UserSettingsEntity, context: unknown): Promise<unknown>;
}

interface UserSettingsRepositoryFactory {
    create(entityName: 'user_config'): UserConfigRepository;
}

interface CurrentUser {
    id?: EntityKey<'user'> | null;
}

/**
 * @private
 *
 * Duplicated in `src/app/composables/use-user-settings`; change both together.
 */
export default Shopware.Mixin.register(
    'user-settings',
    defineComponent({
        inject: [
            'acl',
        ],

        computed: {
            userConfigRepository(): UserConfigRepository {
                const repositoryFactory = (this as unknown as { repositoryFactory: UserSettingsRepositoryFactory })
                    .repositoryFactory;

                return repositoryFactory.create('user_config');
            },

            currentUser(): CurrentUser | null {
                return Shopware.Store.get('session').currentUser as CurrentUser | null;
            },
        },

        methods: {
            /**
             * Receives the whole settings entity via identifier key
             *
             * @param {string} identifier Used to identify its target use
             * @param {string|null} userId Id of the target user; `null` will use the current user
             * @return {Promise<*>}
             */
            getUserSettingsEntity(
                identifier: string,
                userId: EntityKey<'user'> | null = null,
            ): Promise<UserSettingsEntity | null> {
                if (!this.acl.can('user_config:read')) {
                    return Promise.reject();
                }

                return this.userConfigRepository
                    .search(this.userGridSettingsCriteria(identifier, userId), Shopware.Context.api)
                    .then((response) => {
                        if (!response.length) {
                            return null;
                        }

                        return response[0];
                    });
            },

            /**
             * Receives settings values via identifier key
             *
             * @param {string} identifier Used to identify its target use
             * @param {string|null} userId Id of the target user; `null` will use the current user
             * @return {Promise<*>}
             */
            async getUserSettings(identifier: string, userId = null): Promise<unknown> {
                if (!this.acl.can('user_config:read')) {
                    return Promise.reject();
                }

                if (!userId || userId === this.currentUser?.id) {
                    const response = await Shopware.Service('userConfigService').search([identifier]);

                    return response?.data?.[identifier] ?? null;
                }

                const entity = await this.getUserSettingsEntity(identifier, userId);

                if (!entity) {
                    return null;
                }

                return entity.value;
            },

            /**
             * Saves settings to the currently logged in user
             *
             * @param {string} identifier Unique key to identify its target use
             * @param {{[key: string]: any}} entityValue Values to save
             * @param {string|null} userId Id of the target user; `null` will use the current user
             * @return {Promise<*>}
             */
            async saveUserSettings(
                identifier: string,
                entityValue: {
                    // eslint-disable-next-line @typescript-eslint/no-explicit-any
                    [key: string]: any;
                },
                userId: EntityKey<'user'> | null = null,
            ): Promise<unknown> {
                if (!this.acl.can('user_config:create') || !this.acl.can('user_config:update')) {
                    return Promise.reject();
                }

                if (!identifier) {
                    return Promise.reject();
                }

                if (!identifier.includes('.')) {
                    identifier = `custom.${identifier}`;
                }

                if (!userId) {
                    userId = this.currentUser?.id ?? null;
                }

                if (!userId || userId === this.currentUser?.id) {
                    return Shopware.Service('userConfigService').upsert({
                        [identifier]: entityValue,
                    });
                }

                let userSettings: UserSettingsEntity | null = await this.getUserSettingsEntity(identifier, userId);
                if (!userSettings) {
                    userSettings = this.userConfigRepository.create(Shopware.Context.api);
                }

                const entityData = Object.assign(userSettings, {
                    userId,
                    key: identifier,
                    value: entityValue,
                });

                return this.userConfigRepository.save(entityData, Shopware.Context.api);
            },

            /**
             * Provides the userSettings criteria used for the queries
             *
             * @internal
             * @param {string} identifier Used to identify its target use
             * @param {string|null} userId Id of the target user; `null` will use the current user
             * @return {Criteria}
             */
            userGridSettingsCriteria(identifier: string, userId: EntityKey<'user'> | null = null) {
                if (!userId) {
                    userId = this.currentUser?.id ?? ('' as EntityKey<'user'>);
                }

                const criteria = new Shopware.Data.Criteria(1, 25);
                criteria.addFilter(Shopware.Data.Criteria.equals('key', identifier));
                criteria.addFilter(Shopware.Data.Criteria.equals('userId', userId));

                return criteria;
            },
        },
    }),
);
