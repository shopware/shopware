/**
 * @sw-package framework
 */

import template from './sw-login-recovery-info.html.twig';
import { wrapComponentConfig } from '../dependencies';

/**
 * @private
 */
export default wrapComponentConfig({
    template,

    emits: ['is-not-loading'],

    computed: {
        email(): string | null {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-unsafe-member-access
            const email = window.history.state?.email;

            return typeof email === 'string' && email.length ? email : null;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.$emit('is-not-loading');
        },
    },
});
