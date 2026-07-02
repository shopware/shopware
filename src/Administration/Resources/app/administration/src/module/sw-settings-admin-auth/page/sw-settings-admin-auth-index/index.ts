import { defineComponent } from 'vue';
import template from './sw-settings-admin-auth-index.html.twig';
import './sw-settings-admin-auth-index.scss';

const { Mixin } = Shopware;

const METHODS_CONFIG_KEY = 'core.adminAuth.methods';
const CHALLENGE_TTL_CONFIG_KEY = 'core.adminAuth.mfaChallengeTtlSeconds';
const CONFIG_DOMAIN = 'core.adminAuth';
const DEFAULT_CHALLENGE_TTL = 300;

interface MethodRow {
    type: string;
    enabled: boolean;
    isPrimary: boolean;
    isSecondFactor: boolean;
    priority: number;
    canBePrimary: boolean;
    canBeSecondFactor: boolean;
    fallback: boolean;
}

type StoredMethodSettings = Record<
    string,
    {
        enabled?: boolean;
        isPrimary?: boolean;
        isSecondFactor?: boolean;
        priority?: number;
    }
>;

/**
 * Capability matrix of the built-in login methods, mirroring the backend's
 * MethodSettingsService. `canBePrimary` / `canBeSecondFactor` are hard limits;
 * the remaining values are the defaults applied when nothing is stored yet.
 */
const METHOD_DEFAULTS: MethodRow[] = [
    {
        type: 'password',
        enabled: true,
        isPrimary: true,
        isSecondFactor: false,
        priority: 100,
        canBePrimary: true,
        canBeSecondFactor: false,
        fallback: false,
    },
    {
        type: 'webauthn',
        enabled: true,
        isPrimary: true,
        isSecondFactor: true,
        priority: 90,
        canBePrimary: true,
        canBeSecondFactor: true,
        fallback: false,
    },
    {
        type: 'totp',
        enabled: true,
        isPrimary: false,
        isSecondFactor: true,
        priority: 80,
        canBePrimary: false,
        canBeSecondFactor: true,
        fallback: false,
    },
    {
        type: 'recovery_codes',
        enabled: true,
        isPrimary: false,
        isSecondFactor: true,
        priority: 10,
        canBePrimary: false,
        canBeSecondFactor: true,
        fallback: true,
    },
];

const METHOD_ICONS: Record<string, string> = {
    password: 'regular-key',
    webauthn: 'regular-fingerprint',
    totp: 'regular-mobile',
    recovery_codes: 'regular-undo',
};

/**
 * Runtime settings for the built-in login methods and the MFA challenge session.
 *
 * The values live in the system config (`core.adminAuth.*`) — the runtime-editable
 * layer beneath the YAML configuration. A method disabled via YAML
 * (`shopware.admin_auth.password_login` / `shopware.admin_auth.mfa.methods.*`) stays
 * off no matter what is stored here; the backend clamps the stored settings on read.
 *
 * @sw-package framework
 * @private
 */
export default defineComponent({
    template,

    inject: [
        'systemConfigApiService',
        'acl',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    data() {
        return {
            methods: [] as MethodRow[],
            mfaChallengeTtlSeconds: DEFAULT_CHALLENGE_TTL,
            isLoading: false,
            isSaveSuccessful: false,
        };
    },

    computed: {
        isEditable(): boolean {
            return this.acl.can('admin_auth.editor');
        },
    },

    created() {
        void this.loadConfig();
    },

    methods: {
        async loadConfig() {
            this.isLoading = true;

            try {
                const values = (await this.systemConfigApiService.getValues(CONFIG_DOMAIN, null)) as Record<string, unknown>;

                this.methods = this.mergeStoredMethods(values[METHODS_CONFIG_KEY]);

                const ttl = Number(values[CHALLENGE_TTL_CONFIG_KEY]);
                this.mfaChallengeTtlSeconds = Number.isFinite(ttl) && ttl > 0 ? ttl : DEFAULT_CHALLENGE_TTL;
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-settings-admin-auth.methods.loadError'),
                });
            } finally {
                this.isLoading = false;
            }
        },

        /**
         * Merges the stored runtime settings over the capability defaults, clamping every
         * value to what the method is able to do — the same merge the backend applies.
         */
        mergeStoredMethods(stored: unknown): MethodRow[] {
            const storedSettings: StoredMethodSettings =
                typeof stored === 'object' && stored !== null ? (stored as StoredMethodSettings) : {};

            return METHOD_DEFAULTS.map((defaults) => {
                const override = storedSettings[defaults.type] ?? {};

                return {
                    ...defaults,
                    enabled: Boolean(override.enabled ?? defaults.enabled),
                    isPrimary: defaults.canBePrimary && Boolean(override.isPrimary ?? defaults.isPrimary),
                    isSecondFactor:
                        defaults.canBeSecondFactor && Boolean(override.isSecondFactor ?? defaults.isSecondFactor),
                    priority: Number(override.priority ?? defaults.priority),
                };
            }).sort((a, b) => b.priority - a.priority);
        },

        methodIcon(type: string): string {
            return METHOD_ICONS[type] ?? 'regular-lock';
        },

        methodName(type: string): string {
            return this.$t(`sw-settings-admin-auth.methods.types.${type}.name`);
        },

        methodDescription(type: string): string {
            return this.$t(`sw-settings-admin-auth.methods.types.${type}.description`);
        },

        async onSave() {
            this.isLoading = true;
            this.isSaveSuccessful = false;

            const methodsPayload: StoredMethodSettings = {};
            this.methods.forEach((method) => {
                methodsPayload[method.type] = {
                    enabled: method.enabled,
                    isPrimary: method.canBePrimary && method.isPrimary,
                    isSecondFactor: method.canBeSecondFactor && method.isSecondFactor,
                    priority: method.priority,
                };
            });

            const ttl = Number(this.mfaChallengeTtlSeconds);

            try {
                await this.systemConfigApiService.saveValues(
                    {
                        [METHODS_CONFIG_KEY]: methodsPayload,
                        [CHALLENGE_TTL_CONFIG_KEY]: Number.isFinite(ttl) && ttl > 0 ? ttl : DEFAULT_CHALLENGE_TTL,
                    },
                    null,
                );

                this.isSaveSuccessful = true;
                this.createNotificationSuccess({
                    message: this.$t('sw-settings-admin-auth.methods.saveSuccess'),
                });
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-settings-admin-auth.methods.saveError'),
                });
            } finally {
                this.isLoading = false;
            }
        },
    },
});
