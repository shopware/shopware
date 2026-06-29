/**
 * @sw-package framework
 */

type AdminUserConfigValues = Record<string, unknown>;

interface UserConfigService {
    search(keys?: string[] | null): Promise<{ data?: AdminUserConfigValues } | undefined>;
    upsert(values: AdminUserConfigValues): Promise<void>;
}

interface AdminUserConfigState {
    configs: AdminUserConfigValues;
    loaded: boolean;
    userId: string | null;
}

interface AdminUserConfigSessionUser {
    id?: string | null;
}

let pendingLoad: Promise<AdminUserConfigValues> | null = null;

const adminUserConfigStore = Shopware.Store.register({
    id: 'adminUserConfig',

    state: (): AdminUserConfigState => {
        return {
            configs: {},
            loaded: false,
            userId: null,
        };
    },

    actions: {
        getCurrentUserId(): string | null {
            const currentUser = Shopware.Store.get('session').currentUser as AdminUserConfigSessionUser | null;

            return currentUser?.id ?? null;
        },

        ensureCurrentUser(): void {
            const currentUserId = this.getCurrentUserId();

            if (this.userId === currentUserId) {
                return;
            }

            this.configs = {};
            this.loaded = false;
            this.userId = currentUserId;
        },

        async load(): Promise<AdminUserConfigValues> {
            this.ensureCurrentUser();

            if (this.loaded) {
                return this.configs;
            }

            if (pendingLoad) {
                return pendingLoad;
            }

            const userConfigService = Shopware.Service('userConfigService') as unknown as UserConfigService;

            pendingLoad = userConfigService
                .search(null)
                .then((response) => {
                    this.configs = response?.data ?? {};
                    this.loaded = true;
                    this.userId = this.getCurrentUserId();

                    return this.configs;
                })
                .finally(() => {
                    pendingLoad = null;
                });

            return pendingLoad;
        },

        async get<T = unknown>(key: string): Promise<T | undefined> {
            this.ensureCurrentUser();

            if (Object.hasOwn(this.configs, key)) {
                return this.configs[key] as T | undefined;
            }

            const configs = await this.load();

            return configs[key] as T | undefined;
        },

        async upsert(values: AdminUserConfigValues): Promise<void> {
            this.ensureCurrentUser();

            const wasLoaded = this.loaded;
            const userConfigService = Shopware.Service('userConfigService') as unknown as UserConfigService;

            await userConfigService.upsert(values);

            this.configs = {
                ...this.configs,
                ...values,
            };
            this.loaded = wasLoaded;
            this.userId = this.getCurrentUserId();
        },

        invalidate(): void {
            this.configs = {};
            this.loaded = false;
            this.userId = this.getCurrentUserId();
        },
    },
});

/**
 * @private
 */
export type AdminUserConfigStore = ReturnType<typeof adminUserConfigStore>;

/**
 * @private
 */
export default adminUserConfigStore;
