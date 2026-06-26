import './sw-login-login-v2.scss';
import template from './sw-login-login-v2.html.twig';
import type { LoginConfig } from '../../../../core/service/login.service';
import { parseApiRejection } from '../../service/login-error';
import { HTTP_STATUS, ROUTES, STORAGE_KEYS, TIMING } from '../../service/login.constants';

interface CredentialsData {
    loginConfig: LoginConfig | null;
    ssoRedirecting: boolean;
    username: string;
    password: string;
    rememberMe: boolean;
    isLoggingIn: boolean;
    error: boolean;
    retryAfterSeconds: number;
    retryTimer: number | null;
}

/**
 * @sw-package framework
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    inject: {
        loginService: {
            from: 'loginService',
        },
    },

    mixins: [
        Shopware.Mixin.getByName('notification'),
    ],

    emits: ['update:loading'],

    data(): CredentialsData {
        return {
            loginConfig: null,
            ssoRedirecting: false,
            username: '',
            password: '',
            rememberMe: false,
            isLoggingIn: false,
            error: false,
            retryAfterSeconds: 0,
            retryTimer: null,
        };
    },

    created() {
        void this.createdComponent();
    },

    beforeUnmount() {
        this.stopRetryCountdown();
    },

    computed: {
        isSsoConfigured(): boolean {
            return !!this.loginConfig?.url;
        },

        isSsoMandatory(): boolean {
            return this.isSsoConfigured && this.loginConfig?.useDefault === false;
        },

        showSso(): boolean {
            return this.isSsoConfigured && this.loginConfig?.useDefault === true;
        },

        isRateLimited(): boolean {
            return this.retryAfterSeconds > 0;
        },

        countdownLabel(): string {
            const minutes = Math.floor(this.retryAfterSeconds / TIMING.SECONDS_PER_MINUTE);
            const seconds = this.retryAfterSeconds % TIMING.SECONDS_PER_MINUTE;

            return `${minutes}:${seconds.toString().padStart(2, '0')}`;
        },

        canSubmit(): boolean {
            return this.username.length > 2 && this.password.length > 2;
        },

        isDisabled(): boolean {
            return this.isLoggingIn || this.ssoRedirecting || this.isRateLimited;
        },
    },

    methods: {
        async createdComponent(): Promise<void> {
            await Promise.all([
                this.ensureAdminLocale(),
                this.loadLoginConfig(),
            ]);
        },

        async ensureAdminLocale(): Promise<void> {
            if (localStorage.getItem(STORAGE_KEYS.ADMIN_LOCALE)) {
                return;
            }

            const localeFactory = Shopware.Application.getContainer('factory').locale;
            await Shopware.Store.get('session').setAdminLocale(localeFactory.getLastKnownLocale());
        },

        async loadLoginConfig(): Promise<void> {
            try {
                this.loginConfig = await this.loginService.getLoginTemplateConfig();
            } catch {
                this.loginConfig = null;
            }

            if (this.isSsoMandatory) {
                this.forwardToSso();
            }
        },

        forwardToSso(): void {
            if (!this.loginConfig?.url) {
                return;
            }

            this.ssoRedirecting = true;
            this.$emit('update:loading', true);

            sessionStorage.setItem(STORAGE_KEYS.REDIRECT_FROM_LOGIN, 'true');
            sessionStorage.setItem(STORAGE_KEYS.SSO_SESSION, 'true');

            window.location.href = this.loginConfig.url;
        },

        async onSubmit(): Promise<void> {
            if (!this.canSubmit || this.isLoggingIn || this.isRateLimited) {
                return;
            }

            this.error = false;
            this.isLoggingIn = true;
            this.loginService.setRememberMe(this.rememberMe);

            try {
                await this.loginService.loginByUsername(this.username, this.password);
                await this.handleLoginSuccess();
            } catch (error) {
                this.handleLoginError(error);
            } finally {
                this.isLoggingIn = false;
            }
        },

        handleLoginError(error: unknown): void {
            const { status, retryAfterSeconds } = parseApiRejection(error);

            if (status === HTTP_STATUS.TOO_MANY_REQUESTS) {
                this.startRetryCountdown(retryAfterSeconds ?? 0);

                return;
            }

            if (status !== undefined) {
                this.error = true;
                this.password = '';

                return;
            }

            this.createNotificationError({
                message: this.$t('sw-login-v2.credentials.errorUnexpected'),
            });
        },

        async handleLoginSuccess(): Promise<void> {
            this.password = '';

            await this.forwardLogin();
            this.reloadIfRequested();
        },

        async forwardLogin(): Promise<void> {
            const previousRoute = this.readPreviousRoute();

            if (this.shouldEnterFirstRunWizard()) {
                await this.$router.push({ name: ROUTES.FIRST_RUN_WIZARD });

                return;
            }

            if (previousRoute?.fullPath) {
                await this.$router.push(previousRoute.fullPath);

                return;
            }

            await this.$router.push({ name: ROUTES.CORE });
        },

        shouldEnterFirstRunWizard(): boolean {
            if (!Shopware.Context.app.firstRunWizard) {
                return false;
            }

            const currentName = String(this.$router.currentRoute.value.name ?? '');

            return !currentName.startsWith(ROUTES.FIRST_RUN_WIZARD_PREFIX) && this.$router.hasRoute(ROUTES.FIRST_RUN_WIZARD);
        },

        readPreviousRoute(): { fullPath?: string } | null {
            const raw = sessionStorage.getItem(STORAGE_KEYS.PREVIOUS_ROUTE);
            sessionStorage.removeItem(STORAGE_KEYS.PREVIOUS_ROUTE);

            if (!raw) {
                return null;
            }

            try {
                return JSON.parse(raw) as { fullPath?: string };
            } catch {
                return null;
            }
        },

        reloadIfRequested(): void {
            if (!sessionStorage.getItem(STORAGE_KEYS.SHOULD_RELOAD)) {
                return;
            }

            sessionStorage.removeItem(STORAGE_KEYS.SHOULD_RELOAD);
            window.location.reload();
        },

        startRetryCountdown(seconds: number): void {
            this.stopRetryCountdown();
            this.retryAfterSeconds = seconds;

            if (seconds <= 0) {
                return;
            }

            this.retryTimer = window.setInterval(() => {
                this.retryAfterSeconds -= 1;

                if (this.retryAfterSeconds <= 0) {
                    this.stopRetryCountdown();
                }
            }, TIMING.COUNTDOWN_INTERVAL_MS);
        },

        stopRetryCountdown(): void {
            if (this.retryTimer === null) {
                return;
            }

            window.clearInterval(this.retryTimer);
            this.retryTimer = null;
        },
    },
});
