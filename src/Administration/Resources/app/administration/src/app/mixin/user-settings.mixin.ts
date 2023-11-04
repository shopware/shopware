/**
 * @package admin
 */

/* @private */
export {};

/**
 * @deprecated tag:v6.6.0 - Will be private
 */
Shopware.Mixin.register('user-settings', {
    inject: [
        'acl',
    ],

    computed: {
        userConfigRepository() {
            return this.repositoryFactory.create('user_config');
        },

        currentUser() {
            return Shopware.State.get('session').currentUser;
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
        getUserSettingsEntity(identifier: string, userId: string|null = null) {
            if (!this.acl.can('user_config:read')) {
                return Promise.reject();
            }

            return this.userConfigRepository.search(
                this.userGridSettingsCriteria(identifier, userId),
                Shopware.Context.api,
            ).then((response) => {
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
        async getUserSettings(identifier: string, userId = null) {
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
        async saveUserSettings(identifier: string, entityValue: {
            // eslint-disable-next-line @typescript-eslint/no-explicit-any
            [key: string]: any;
        }, userId: string|null = null) {
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
                userId = this.currentUser?.id;
            }

            let userSettings = await this.getUserSettingsEntity(identifier);
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
        userGridSettingsCriteria(identifier: string, userId: string|null = null) {
            if (!userId) {
                userId = this.currentUser?.id;
            }

            const criteria = new Shopware.Data.Criteria(1, 25);
            criteria.addFilter(Shopware.Data.Criteria.equals('key', identifier));
            criteria.addFilter(Shopware.Data.Criteria.equals('userId', userId));

            return criteria;
        },
    },
});
