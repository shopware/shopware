/**
 * @sw-package after-sales
 */
import template from './sw-settings-mailer-smtp.html.twig';
import './sw-settings-mailer-smtp.scss';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    emits: [
        'host-changed',
        'port-changed',
    ],

    props: {
        mailerSettings: {
            type: Object,
            required: true,
        },
        hostError: {
            type: Object,
            required: false,
            default: null,
        },
        portError: {
            type: Object,
            required: false,
            default: null,
        },
    },

    computed: {
        isOauth() {
            return this.mailerSettings['core.mailerSettings.emailAgent'] === 'smtp+oauth';
        },

        isClientCredentials() {
            return this.mailerSettings['core.mailerSettings.oauthGrantType'] === 'client_credentials' || 
                   !this.mailerSettings['core.mailerSettings.oauthGrantType'];
        },

        isROPC() {
            return this.mailerSettings['core.mailerSettings.oauthGrantType'] === 'password';
        },

        oauthGrantTypeOptions() {
            return [
                {
                    value: 'client_credentials',
                    label: this.$tc('sw-settings-mailer.oauth-grant-type.client-credentials'),
                },
                {
                    value: 'password',
                    label: this.$tc('sw-settings-mailer.oauth-grant-type.password'),
                },
            ];
        },

        encryptionOptions() {
            return [
                {
                    value: 'null',
                    label: this.$tc('sw-settings-mailer.encryption.no-encryption'),
                },
                {
                    value: 'ssl',
                    label: this.$tc('sw-settings-mailer.encryption.ssl'),
                },
                {
                    value: 'tls',
                    label: this.$tc('sw-settings-mailer.encryption.tls'),
                },
            ];
        },
    },
};
