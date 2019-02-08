import { Component, Mixin, State } from 'src/core/shopware';
import { warn } from 'src/core/service/utils/debug.utils';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-category-detail.html.twig';
import './sw-category-detail.scss';

Component.register('sw-category-detail', {
    template,

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder')
    ],

    data() {
        return {
            category: null,
            categories: [],
            isLoading: false,
            mediaItem: null,
            isMobileViewport: null,
            splitBreakpoint: 1024
        };
    },

    computed: {
        categoryStore() {
            return State.getStore('category');
        },

        mediaStore() {
            return State.getStore('media');
        },

        uploadStore() {
            return State.getStore('upload');
        },

        languageStore() {
            return State.getStore('language');
        },

        pageClasses() {
            return {
                'has--category': !!this.category && !this.isLoading,
                'is--mobile': !!this.isMobileViewport
            };
        }
    },

    watch: {
        '$route.params.id'() {
            this.setCategory();
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.isLoading = true;
            this.checkViewport();
            this.registerListener();

            this.getCategories().then(() => {
                this.setCategory();
            });
        },

        registerListener() {
            this.$device.onResize({
                listener: this.checkViewport.bind(this)
            });
        },

        checkViewport() {
            this.isMobileViewport = this.$device.getViewportWidth() < this.splitBreakpoint;
        },

        getCategories(parentId = null) {
            this.isLoading = true;
            return this.categoryStore.getList({
                page: 1,
                limit: 500,
                criteria: CriteriaFactory.equals('category.parentId', parentId)
            }).then((response) => {
                this.isLoading = false;
                this.categories = Object.values(this.categoryStore.store);
                return response.items;
            });
        },

        setCategory() {
            this.isLoading = true;
            const categoryId = this.$route.params.id;

            if (categoryId) {
                this.getCategory(categoryId).then(response => {
                    this.category = response;
                    this.mediaItem = this.category.mediaId
                        ? this.mediaStore.getById(this.category.mediaId) : null;
                    this.isLoading = false;
                });
            } else {
                this.resetCategory();
            }
        },

        onRefreshCategories() {
            this.getCategories();
        },

        onSaveCategories() {
            return this.categoryStore.sync();
        },

        resetCategory() {
            this.$router.push({ name: 'sw.category.index' });
            this.category = null;
            this.mediaItem = null;
            this.isLoading = false;
        },

        onDuplicateCategory(item) {
            this.categoryStore.duplicate(item.id, true);
            this.onSaveCategories().then(() => {
                this.getCategories();
            });
        },

        getCategory(categoryId) {
            return this.categoryStore.getByIdAsync(categoryId);
        },

        onUploadAdded({ uploadTag }) {
            this.isLoading = true;
            this.mediaStore.sync().then(() => {
                return this.uploadStore.runUploads(uploadTag);
            }).finally(() => {
                this.isLoading = false;
            });
        },

        setMediaItem(mediaEntity) {
            this.mediaItem = mediaEntity;
            this.category.mediaId = mediaEntity.id;
        },

        onUnlinkLogo() {
            this.mediaItem = null;
            this.category.mediaId = null;
        },

        onChangeLanguage() {
            this.getCategories().then(() => {
                this.setCategory();
            });
        },

        abortOnLanguageChange() {
            return this.category ? this.category.hasChanges() : false;
        },

        saveOnLanguageChange() {
            return this.onSave();
        },

        onSave() {
            const categoryName = this.category.name || this.category.meta.viewData.name;
            const titleSaveSuccess = this.$tc('sw-category.general.titleSaveSuccess');
            const messageSaveSuccess = this.$tc('sw-category.general.messageSaveSuccess', 0, { name: categoryName });
            const titleSaveError = this.$tc('global.notification.notificationSaveErrorTitle');
            const messageSaveError = this.$tc('global.notification.notificationSaveErrorMessage',
                0, { entityName: categoryName });

            return this.category.save().then(() => {
                this.createNotificationSuccess({
                    title: titleSaveSuccess,
                    message: messageSaveSuccess
                });
            }).catch(exception => {
                this.createNotificationError({
                    title: titleSaveError,
                    message: messageSaveError
                });
                warn(this._name, exception.message, exception.response);
            });
        }
    }
});
