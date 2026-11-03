import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package framework
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-user-sso-invitation-modal',
    load: () => import('./src/sw-user-sso-invitation-modal'),
    public: {
        emits: [
            'user-invited',
            'invitation-failed',
            'modal-close',
        ],
        computed: [
            'invitationService',
            'languageRepository',
            'languageCriteria',
            'hasError',
        ],
        methods: [
            'componentCreated',
            'loadLanguages',
            'sendInvitation',
            'closeModal',
            'validateEmail',
            'validateLanguage',
        ],
    },
});
