import template from './sw-settings-system-health-index.html.twig';
import './sw-settings-system-health-index.scss';
import { needsAttention, type SystemHealthCheck } from '../../types';

// SystemChecker runs categories in this order; a failing SYSTEM check skips the rest
const CATEGORY_ORDER = [
    'SYSTEM',
    'FEATURE',
    'EXTERNAL',
    'AUXILIARY',
];
const GROUPS = ['attention', 'passed', 'not-run'];
const POLL_INTERVAL_MS = 2000;

/**
 * Lists the system checks with their last result. Running checks happens in the background: the page asks the
 * API for the results again until every check it started has a new result.
 *
 * @sw-package framework
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    inject: ['systemHealthApiService'],

    data(): {
        checks: SystemHealthCheck[];
        isLoading: boolean;
        // the checks that were started, with the time of their result before the run
        running: Record<string, string | null>;
        openCheck: string | null;
        openGroups: string[];
        pollTimeout: ReturnType<typeof setTimeout> | null;
    } {
        return {
            checks: [],
            isLoading: false,
            running: {},
            // one check open at a time; opening another closes it
            openCheck: null,
            openGroups: ['attention'],
            pollTimeout: null,
        };
    },

    computed: {
        isRunning(): boolean {
            return Object.keys(this.running).length > 0;
        },

        // the checks by state: problems first and open, passed and not run folded away
        groups(): Array<{ key: string; checks: SystemHealthCheck[] }> {
            const groupOf = (check: SystemHealthCheck): string => {
                if (!check.result) {
                    return 'not-run';
                }

                return needsAttention(check) ? 'attention' : 'passed';
            };

            const sorted = [...this.checks].sort(
                (a, b) => CATEGORY_ORDER.indexOf(a.category) - CATEGORY_ORDER.indexOf(b.category),
            );

            return GROUPS.map((key) => ({
                key,
                checks: sorted.filter((check) => groupOf(check) === key),
            }));
        },
    },

    created() {
        void this.loadChecks();
    },

    beforeUnmount() {
        this.stopPolling();
    },

    methods: {
        async loadChecks(): Promise<void> {
            this.isLoading = true;

            try {
                this.checks = await this.systemHealthApiService.getChecks();
            } catch {
                this.notifyError();
            } finally {
                this.isLoading = false;
            }
        },

        /**
         * Starts one check, or all of them without a name.
         */
        async run(name: string | null = null): Promise<void> {
            const started = this.checks.filter((check) => name === null || check.name === name);
            started.forEach((check) => {
                this.running = { ...this.running, [check.name]: check.result?.checkedAt ?? null };
            });

            try {
                await this.systemHealthApiService.run(name);
                this.schedulePoll();
            } catch {
                this.running = {};
                this.notifyError();
            }
        },

        schedulePoll(): void {
            this.stopPolling();
            this.pollTimeout = setTimeout(() => {
                void this.poll();
            }, POLL_INTERVAL_MS);
        },

        async poll(): Promise<void> {
            try {
                this.checks = await this.systemHealthApiService.getChecks();
            } catch {
                this.running = {};
                this.notifyError();

                return;
            }

            // a check is done once its result is newer than the one it had when it was started
            this.running = Object.fromEntries(
                Object.entries(this.running).filter(([name, previous]) => {
                    const check = this.checks.find((candidate) => candidate.name === name);

                    return !!check && (check.result?.checkedAt ?? null) === previous;
                }),
            );

            if (this.isRunning) {
                this.schedulePoll();
            }
        },

        stopPolling(): void {
            if (this.pollTimeout) {
                clearTimeout(this.pollTimeout);
                this.pollTimeout = null;
            }
        },

        toggleGroup(key: string): void {
            if (!this.groups.find((group) => group.key === key)?.checks.length) {
                return;
            }

            this.openGroups = this.openGroups.includes(key)
                ? this.openGroups.filter((group) => group !== key)
                : [...this.openGroups, key];
        },

        toggleCheck(name: string): void {
            this.openCheck = this.openCheck === name ? null : name;
        },

        notifyError(): void {
            Shopware.Store.get('notification').createNotification({
                variant: 'critical',
                message: this.$t('sw-settings-system-health.index.loading-error'),
            });
        },
    },
});
