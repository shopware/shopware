/* eslint-disable @typescript-eslint/prefer-promise-reject-errors */
/**
 * @sw-package framework
 *
 * @experimental stableVersion:v6.9.0 feature:ADMIN_MIXIN_COMPOSABLES
 */
import Criteria from 'src/core/data/criteria.data';

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

interface CurrentUser {
    id?: EntityKey<'user'> | null;
}

/**
 * Composable alternative to the `user-settings` mixin: reads and writes per-user config.
 *
 * The mixin injected `acl`, read the component's injected `repositoryFactory` and exposed
 * `currentUser` / `userConfigRepository` as computeds. This composable resolves all of them itself,
 * through `Shopware.Service` and the session store, so it needs nothing from the component. The mixin
 * stays in place for Options API components.
 *
 * Keep this and `src/app/mixin/user-settings.mixin.ts` in sync — change both together.
 *
 * @private
 */
export default function useUserSettings(): {
    getUserSettingsEntity: (identifier: string, userId?: string | null) => Promise<UserSettingsEntity | null>;
    getUserSettings: (identifier: string, userId?: string | null) => Promise<unknown>;
    saveUserSettings: (
        identifier: string,
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        entityValue: { [key: string]: any },
        userId?: string | null,
    ) => Promise<unknown>;
    userGridSettingsCriteria: (identifier: string, userId?: string | null) => Criteria;
} {
    function acl(): { can: (privilege: string) => boolean } {
        return Shopware.Service('acl');
    }

    function userConfigRepository(): UserConfigRepository {
        return Shopware.Service('repositoryFactory').create('user_config') as unknown as UserConfigRepository;
    }

    function currentUser(): CurrentUser | null {
        return Shopware.Store.get('session').currentUser as CurrentUser | null;
    }

    /**
     * Receives the whole settings entity via identifier key.
     */
    function getUserSettingsEntity(identifier: string, userId: string | null = null): Promise<UserSettingsEntity | null> {
        if (!acl().can('user_config:read')) {
            return Promise.reject();
        }

        return userConfigRepository()
            .search(userGridSettingsCriteria(identifier, userId), Shopware.Context.api)
            .then((response) => {
                if (!response.length) {
                    return null;
                }

                return response[0];
            });
    }

    /**
     * Receives settings values via identifier key.
     */
    async function getUserSettings(identifier: string, userId: string | null = null): Promise<unknown> {
        if (!acl().can('user_config:read')) {
            return Promise.reject();
        }

        if (!userId || userId === currentUser()?.id) {
            const response = await Shopware.Service('userConfigService').search([identifier]);

            return response?.data?.[identifier] ?? null;
        }

        const entity = await getUserSettingsEntity(identifier, userId);

        if (!entity) {
            return null;
        }

        return entity.value;
    }

    /**
     * Saves settings to the target user, defaulting to the currently logged in one.
     */
    async function saveUserSettings(
        identifier: string,
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        entityValue: { [key: string]: any },
        userId: string | null = null,
    ): Promise<unknown> {
        if (!acl().can('user_config:create') || !acl().can('user_config:update')) {
            return Promise.reject();
        }

        if (!identifier) {
            return Promise.reject();
        }

        if (!identifier.includes('.')) {
            identifier = `custom.${identifier}`;
        }

        if (!userId) {
            userId = currentUser()?.id ?? null;
        }

        if (!userId || userId === currentUser()?.id) {
            return Shopware.Service('userConfigService').upsert({
                [identifier]: entityValue,
            });
        }

        let userSettings: UserSettingsEntity | null = await getUserSettingsEntity(identifier, userId);

        if (!userSettings) {
            userSettings = userConfigRepository().create(Shopware.Context.api);
        }

        const entityData = Object.assign(userSettings, {
            userId,
            key: identifier,
            value: entityValue,
        });

        return userConfigRepository().save(entityData, Shopware.Context.api);
    }

    /**
     * Provides the userSettings criteria used for the queries.
     */
    function userGridSettingsCriteria(identifier: string, userId: string | null = null): Criteria {
        if (!userId) {
            userId = currentUser()?.id ?? '';
        }

        const criteria = new Criteria(1, 25);

        criteria.addFilter(Criteria.equals('key', identifier));
        criteria.addFilter(Criteria.equals('userId', userId));

        return criteria;
    }

    return {
        getUserSettingsEntity,
        getUserSettings,
        saveUserSettings,
        userGridSettingsCriteria,
    };
}
