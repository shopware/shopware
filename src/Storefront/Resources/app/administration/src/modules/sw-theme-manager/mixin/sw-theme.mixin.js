/**
 * @package sales-channel
 */

const { Mixin } = Shopware;

Mixin.register('theme', {
    inject: [
        'repositoryFactory',
        'themeService',
        'acl'
    ],

    data() {
        return {
            showDeleteModal: false,
            showMediaModal: false,
            showRenameModal: false,
            showDuplicateModal: false,
            newThemeName: '',
            modalTheme: null
        };
    },

    computed: {
        themeRepository() {
            return this.repositoryFactory.create('theme');
        }
    },

    methods: {
        onDeleteTheme(theme) {
            if (!this.acl.can('theme.deleter')) {
                return;
            }

            this.modalTheme = theme;
            this.showDeleteModal = true;
        },

        onCloseDeleteModal() {
            this.showDeleteModal = false;
            this.modalTheme = null;
        },

        onConfirmThemeDelete() {
            this.deleteTheme(this.modalTheme);

            this.showDeleteModal = false;
            this.modalTheme = null;
        },

        deleteTheme(theme) {
            const titleDeleteError = this.$tc('sw-theme-manager.components.themeListItem.notificationDeleteErrorTitle');
            const messageDeleteError = this.$tc('sw-theme-manager.components.themeListItem.notificationDeleteErrorMessage');

            this.isLoading = true;
            this.themeRepository.delete(theme.id, Shopware.Context.api).then(() => {
                if (this.getList) {
                    this.getList();
                    return;
                }

                this.$router.push({ name: 'sw.theme.manager.index' });
            }).catch(() => {
                this.isLoading = false;
                this.createNotificationError({
                    title: titleDeleteError,
                    message: messageDeleteError
                });
            });
        },

        onDuplicateTheme(theme) {
            if (!this.acl.can('theme.creator')) {
                return;
            }

            this.modalTheme = theme;
            this.showDuplicateModal = true;
        },

        onCloseDuplicateModal() {
            this.showDuplicateModal = false;
            this.modalTheme = null;
            this.newThemeName = '';
        },

        onConfirmThemeDuplicate() {
            this.duplicateTheme(this.modalTheme, this.newThemeName);

            this.showDuplicateModal = false;
            this.modalTheme = null;
            this.newThemeName = '';
        },

        duplicateTheme(parentTheme, name) {
            const themeDuplicate = this.themeRepository.create(Shopware.Context.api);

            themeDuplicate.name = name;
            themeDuplicate.parentThemeId = parentTheme.id;
            themeDuplicate.author = parentTheme.author;
            themeDuplicate.description = parentTheme.description;
            themeDuplicate.labels = parentTheme.labels;
            themeDuplicate.helpTexts = parentTheme.helpTexts;
            themeDuplicate.customFields = parentTheme.customFields;
            themeDuplicate.baseConfig = null;
            themeDuplicate.configValues = null;
            themeDuplicate.previewMediaId = parentTheme.previewMediaId;
            themeDuplicate.active = true;

            this.themeRepository.save(themeDuplicate, Shopware.Context.api).then(() => {
                this.$router.push({ name: 'sw.theme.manager.detail', params: { id: themeDuplicate.id } });
            });
        },

        onRenameTheme(theme) {
            if (!this.acl.can('theme.editor')) {
                return;
            }

            this.modalTheme = theme;
            this.newThemeName = this.modalTheme.name;
            this.showRenameModal = true;
        },

        onCloseRenameModal() {
            this.showRenameModal = false;
            this.modalTheme = null;
            this.newThemeName = '';
        },

        onConfirmThemeRename() {
            this.RenameTheme(this.modalTheme, this.newThemeName);

            this.showRenameModal = false;
            this.modalTheme = null;
            this.newThemeName = '';
        },

        RenameTheme(theme, name) {
            if (name) {
                theme.name = name;
            }

            this.themeRepository.save(theme, Shopware.Context.api);
        }
    }
});
