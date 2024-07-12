/**
 * @package admin
 *
 * @private
 * @description
 * The component catches all errors in subcomponent which aren't handled before.
 * @status ready
 * @example-type code-only
 * @component-example
 * <sw-error-boundary>
 *     <!-- Your components -->
 * </sw-error-boundary>
 */
Shopware.Component.register('sw-error-boundary', {

    render() {
        if (typeof this.$slots.default === 'function') {
            return this.$slots.default();
        }

        return this.$slots.default;
    },

    inject: ['repositoryFactory'],

    compatConfig: Shopware.compatConfig,

    computed: {
        logEntryRepository() {
            return this.repositoryFactory.create('log_entry', null, {
                keepApiErrors: true,
            });
        },
    },

    errorCaptured(err, vm) {
        // Show more detailed error messages in development mode
        if (process.env.NODE_ENV === 'development') {
            return true;
        }

        console.error('An error was captured in current module:', err);

        this.logErrorInEntries(err, vm);

        // stop error propagation
        return false;
    },

    methods: {
        logErrorInEntries(err, vm) {
            if (!err) {
                return;
            }

            const newLogEntry = this.logEntryRepository.create();

            newLogEntry.message = err.toString();
            newLogEntry.channel = 'Administration';
            newLogEntry.level = 400;
            newLogEntry.context = {
                component: vm?._name ?? 'Unknown component',
                stack: err.stack ?? 'Unknown stack',
                url: window.location.href,
            };

            this.logEntryRepository.save(newLogEntry).catch(e => Shopware.Utils.debug.error(e));
        },
    },
});
