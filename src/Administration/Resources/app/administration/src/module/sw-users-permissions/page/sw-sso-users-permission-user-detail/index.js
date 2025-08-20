/**
 * @internal
 * @sw-package framework
 */
import template from './sw-sso-users-permission-user-detail.html.twig';
import './sw-sso-users-permissions-user-detail.scss';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

const MODE = Object.freeze({
    VIEW: 'view',
    EDIT: 'edit',
    CREATE: 'create',
});

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'acl',
        'repositoryFactory',
        'integrationService',
        'userService',
    ],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('salutation'),
    ],

    shortcuts: {
        'SYSTEMKEY+S': 'onSave',
        ESCAPE: 'onCancel',
    },

    created() {
        this.createdComponent();
    },

    data() {
        return {
            isLoading: false,
            userId: null,
            user: null,
            currentUser: null,
            languages: [],
            timezoneOptions: [],
            integrations: [],
            keyIdToDelete: null,
            showAccessKeyDeleteModal: false,
            isCreateAccessKeyModalOpen: false,
            newAccessKey: '',
            newSecretAccessKey: '',
            editMode: MODE.CREATE,
        };
    },

    computed: {
        fullName() {
            return this.salutation(this.user, this.$tc('sw-users-permissions.users.user-detail.labelNewUser'));
        },

        tooltipCancel() {
            return {
                message: 'ESC',
                appearance: 'light',
            };
        },

        tooltipSave() {
            const systemKey = this.$device.getSystemKey();

            return {
                message: `${systemKey} + S`,
                appearance: 'light',
            };
        },

        integrationColumns() {
            return [
                {
                    property: 'accessKey',
                    label: this.$tc('sw-users-permissions.users.user-detail.labelAccessKey'),
                },
            ];
        },

        aclRoleCriteria() {
            const criteria = new Criteria(1, 25);

            // Roles created by apps should not be assignable in the admin
            criteria.addFilter(Criteria.equals('app.id', null));
            criteria.addFilter(Criteria.equals('deletedAt', null));

            return criteria;
        },

        isInvited() {
            return this.user.email === this.user.firstName && this.user.email === this.user.lastName && !this.user.active;
        },

        isCurrentUser() {
            if (!this.user || !this.currentUser) {
                return false;
            }

            return this.userId === this.currentUser.id;
        },

        userRepository() {
            return this.repositoryFactory.create('user');
        },

        languageRepository() {
            return this.repositoryFactory.create('language');
        },

        keyRepository() {
            return this.repositoryFactory.create('user_access_key');
        },
    },

    methods: {
        async createdComponent() {
            this.userId = this.$route.params.id;

            this.isLoading = true;
            await Promise.all([
                this.loadUser(),
                this.loadCurrentUser(),
                this.loadLanguages(),
            ]);
            this.isLoading = false;

            this.timezoneOptions = Shopware.Service('timezoneService').getTimezoneOptions();
        },

        loadUser() {
            const criteria = new Criteria(1, 25);

            criteria.addAssociation('accessKeys');
            criteria.addAssociation('locale');
            criteria.addAssociation('aclRoles');
            criteria.addAssociation('avatarMedia');

            return this.userRepository.get(this.userId, Shopware.Context.api, criteria).then((user) => {
                this.user = user;
                this.integrations = this.user.accessKeys;
            });
        },

        loadCurrentUser() {
            return this.userService.getUser().then((response) => {
                this.currentUser = response.data;
            });
        },

        loadLanguages() {
            const languageCriteria = new Criteria(1, 500);

            languageCriteria.addAssociation('locale');
            languageCriteria.addSorting(Criteria.sort('locale.name', 'ASC'));
            languageCriteria.addSorting(Criteria.sort('locale.territory', 'ASC'));

            return this.languageRepository.search(languageCriteria).then((result) => {
                this.languages = [];
                result.forEach((language) => {
                    this.languages.push({
                        id: language.locale.id,
                        value: language.locale.id,
                        label: `${language.locale.translated.name} (${language.locale.translated.territory})`,
                    });
                });
            });
        },

        onCancel() {
            this.$router.push({ name: 'sw.users.permissions.index' });
        },

        onSave() {
            this.isLoading = true;
            return this.userRepository
                .save(this.user, { ...Shopware.Context.api })
                .catch(() => {
                    this.createNotificationError({ message: this.$t('global.notification.unspecifiedSaveErrorMessage') });
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        setMediaItem({ targetId }) {
            this.user.avatarId = targetId;
        },

        onRemoveMedia() {
            this.user.avatarMedia = null;
            this.user.avatarId = null;
        },

        onDropMedia(mediaItem) {
            this.setMediaItem({ targetId: mediaItem.id });
        },

        onCreateAccessKey() {
            this.editMode = MODE.CREATE;
            this.isCreateAccessKeyModalOpen = true;
            this.generateKey();
        },

        generateKey() {
            this.isLoading = true;
            return this.integrationService
                .generateKey({}, {}, true)
                .then((response) => {
                    this.newAccessKey = response.accessKey;
                    this.newSecretAccessKey = response.secretAccessKey;
                })
                .catch(() => {
                    this.createNotificationError({ message: this.$t('global.notification.unspecifiedSaveErrorMessage') });
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        onAccessKeyCreateCancel() {
            this.isCreateAccessKeyModalOpen = false;
            this.newAccessKey = '';
            this.newSecretAccessKey = '';
        },

        onSaveAccessKey(keyObject) {
            this.isLoading = true;
            this.onAccessKeyCreateCancel();

            if (this.editMode === MODE.EDIT) {
                const key = this.user.accessKeys.get(this.editKeyId);
                key.accessKey = keyObject.accessKey;
                key.secretAccessKey = keyObject.secretAccessKey;

                this.isLoading = false;
                return this.onSave();
            }

            const newKey = this.keyRepository.create();
            newKey.quantityStart = 1;
            newKey.accessKey = keyObject.accessKey;
            newKey.secretAccessKey = keyObject.secretAccessKey;
            newKey.userId = this.userId;

            this.user.accessKeys.add(newKey);

            this.isLoading = false;
            return this.onSave();
        },

        onEditAccessKey(keyId) {
            this.editMode = MODE.VIEW;
            this.isCreateAccessKeyModalOpen = true;

            const key = this.user.accessKeys.get(keyId);

            this.editKeyId = key.id;
            this.newAccessKey = key.accessKey;
            this.newSecretAccessKey = key.secretAccessKey;

            return this.onSave();
        },

        onGenerateNewKey() {
            this.editMode = MODE.EDIT;
            this.generateKey();
        },

        onDeleteAccessKey(keyId) {
            this.keyIdToDelete = keyId;
            this.showAccessKeyDeleteModal = true;
        },

        onCloseAccessKeyDeleteModal() {
            this.keyIdToDelete = null;
            this.showAccessKeyDeleteModal = false;
        },

        onConfirmDeleteAccessKey() {
            this.user.accessKeys.remove(this.keyIdToDelete);
            this.onCloseAccessKeyDeleteModal();
            return this.onSave();
        },
    },
};
