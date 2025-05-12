/**
 * @internal
 * @sw-package after-sales
 */
import template from './sw-saas-users-permission-user-detail.html.twig';
import './sw-saas-users-permissions-user-detail.scss';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

const MODE = {
    VIEW: 'view',
    EDIT: 'edit',
    CREATE: 'create',
};

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
        this.userId = this.$route.params.id;
        this.loadRepositories();
        this.loadUser();
        this.loadCurrentUser();
        this.loadLanguages();

        this.timezoneOptions = Shopware.Service('timezoneService').getTimezoneOptions();
    },

    data() {
        return {
            loadings: 0,
            userId: null,
            user: null,
            currentUser: null,
            languages: [],
            timezoneOptions: [],
            integrations: [],
            userRepository: null,
            mediaRepository: null,
            languageRepository: null,
            keyRepository: null,
            keyIdToDelete: null,
            showAccessKeyDeleteModal: false,
            isCreateAccessKeyModalOpen: false,
            newAccessKey: '',
            newSecretAccessKey: '',
            editMode: MODE.CREATE,
        };
    },

    computed: {
        isLoading() {
            return this.loadings > 0;
        },

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
    },

    methods: {
        registerLoading() {
            this.loadings += 1;
        },

        unregisterLoading() {
            this.loadings -= 1;

            if (this.loadings < 0) {
                this.loadings = 0;
            }
        },

        loadRepositories() {
            this.registerLoading();

            this.userRepository = this.repositoryFactory.create('user');
            this.mediaRepository = this.repositoryFactory.create('media');
            this.languageRepository = this.repositoryFactory.create('language');

            this.unregisterLoading();
        },

        loadUser() {
            this.registerLoading();

            const criteria = new Criteria(1, 25);

            criteria.addAssociation('accessKeys');
            criteria.addAssociation('locale');
            criteria.addAssociation('aclRoles');

            this.userRepository.get(this.userId, Shopware.Context.api, criteria).then((user) => {
                this.user = user;
                this.integrations = this.user.accessKeys;

                // Initialize access key repository
                this.keyRepository = this.repositoryFactory.create(this.user.accessKeys.entity, this.user.accessKeys.source);

                if (this.user.avatarId) {
                    this.loadMedia();
                }

                this.unregisterLoading();
            });
        },

        loadCurrentUser() {
            return this.userService.getUser().then((response) => {
                this.currentUser = response.data;
            });
        },

        loadMedia() {
            this.registerLoading();

            this.mediaRepository.get(this.user.avatarId).then((media) => {
                this.user.avatarMedia = media;
                this.unregisterLoading();
            });
        },

        loadLanguages() {
            this.registerLoading();

            const languageCriteria = new Criteria(1, 500);

            languageCriteria.addAssociation('locale');
            languageCriteria.addSorting(Criteria.sort('locale.name', 'ASC'));
            languageCriteria.addSorting(Criteria.sort('locale.territory', 'ASC'));

            this.languageRepository.search(languageCriteria).then((result) => {
                this.languages = [];
                result.forEach((language) => {
                    this.languages.push({
                        id: language.locale.id,
                        value: language.locale.id,
                        label: `${language.locale.translated.name} (${language.locale.translated.territory})`,
                    });
                });

                this.unregisterLoading();
            });
        },

        onCancel() {
            this.$router.push({ name: 'sw.users.permissions.index' });
        },

        onSave() {
            this.registerLoading();

            this.userRepository.save(this.user, { ...Shopware.Context.api }).then(() => {
                this.unregisterLoading();
            });
        },

        setMediaItem({ targetId }) {
            this.user.avatarId = targetId;
            this.loadMedia(targetId);
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
            this.registerLoading();

            this.integrationService.generateKey({}, {}, true).then((response) => {
                // TODO: REMOVE AFTER DEBUG
                console.log(response);
                // TODO: REMOVE AFTER DEBUG
                this.newAccessKey = response.accessKey;
                this.newSecretAccessKey = response.secretAccessKey;

                this.unregisterLoading();
            });
        },

        onAccessKeyCreateCancel() {
            this.isCreateAccessKeyModalOpen = false;
            this.newAccessKey = '';
            this.newSecretAccessKey = '';
        },

        onSaveAccessKey(keyObject) {
            this.registerLoading();
            this.onAccessKeyCreateCancel();

            if (this.editMode === MODE.EDIT) {
                const key = this.user.accessKeys.get(this.editKeyId);
                key.accessKey = keyObject.accessKey;
                key.secretAccessKey = keyObject.secretAccessKey;

                this.unregisterLoading();
                this.onSave();
                return;
            }

            const newKey = this.keyRepository.create();
            newKey.quantityStart = 1;
            newKey.accessKey = keyObject.accessKey;
            newKey.secretAccessKey = keyObject.secretAccessKey;

            this.user.accessKeys.add(newKey);

            this.unregisterLoading();
            this.onSave();
        },

        onEditAccessKey(keyId) {
            this.registerLoading();
            this.editMode = MODE.VIEW;
            this.isCreateAccessKeyModalOpen = true;

            const key = this.user.accessKeys.get(keyId);

            this.editKeyId = key.id;
            this.newAccessKey = key.accessKey;
            this.newSecretAccessKey = key.secretAccessKey;

            this.unregisterLoading();
            this.onSave();
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
            this.onSave();
        },
    },
};
