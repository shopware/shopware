/**
 * @sw-package framework
 */

import template from './sw-app-actions.html.twig';
import './sw-app-actions.scss';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { hasOwnProperty } = Shopware.Utils.object;

const actionTypeConstants = Object.freeze({
    ACTION_SHOW_NOTIFICATION: 'notification',
    ACTION_RELOAD_DATA: 'reload',
    ACTION_OPEN_NEW_TAB: 'openNewTab',
    ACTION_OPEN_MODAL: 'openModal',
});

const modalSizeMapping = {
    small: 'small',
    medium: 'default',
    large: 'large',
    fullscreen: 'full',
};

const IFRAME_KEY = 'app.action_button.iframe';

/**
 * @private
 */
export default {
    template,

    extensionApiDevtoolInformation: {
        property: 'ui.actionButton',
        entity: (currentComponent) => `${currentComponent.entity}`,
        view: (currentComponent) => `${currentComponent.view}`,
    },

    inject: [
        'feature',
        'appActionButtonService',
        'repositoryFactory',
        'extensionSdkService',
    ],

    mixins: [Mixin.getByName('notification')],

    data() {
        return {
            actions: [],
            matchedRoutes: [],
            isOpenModal: false,
            isOpenConfirmModal: false,
            title: '',
            action: null,
            size: 'default',
            isExpanded: false,
            iframeUrl: '',
            isShowModalConfirm: true,
            iframeUserConfig: null,
        };
    },

    computed: {
        entity() {
            return this.$route?.meta?.$module?.entity;
        },

        view() {
            const matchedRoute = this.matchedRoutes
                .filter((match) => {
                    return !!match?.meta?.appSystem?.view;
                })
                .pop();

            return matchedRoute?.meta?.appSystem?.view;
        },

        areActionsAvailable() {
            return !!this.actions && this.actions.length > 0 && this.params.length > 0;
        },

        params() {
            return Shopware.Store.get('shopwareApps').selectedIds;
        },

        userConfigRepository() {
            return this.repositoryFactory.create('user_config');
        },

        currentUser() {
            return Shopware.Store.get('session').currentUser;
        },

        userConfigCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addFilter(Criteria.equals('key', IFRAME_KEY));
            criteria.addFilter(Criteria.equals('userId', this.currentUser?.id));

            return criteria;
        },

        extensionSdkButtons() {
            return Shopware.Store.get('actionButtons').buttons.filter((button) => {
                return button.entity === this.entity && button.view === this.view;
            });
        },

        // Track registrations separately because filtering the store creates a new array for every store change.
        // Watching that array would also reload actions for buttons belonging to unrelated views.
        extensionSdkButtonCount() {
            return Shopware.Store.get('actionButtons').buttons.length;
        },
    },

    watch: {
        $route: {
            immediate: true,
            handler() {
                const entity = this.entity;
                const view = this.view;

                this.matchedRoutes = this.$router.currentRoute.value.matched;

                // Listing routes update query parameters for pagination, filters and sorting. Action buttons only
                // depend on the entity and view, so those query-only route changes must not trigger another request.
                if (entity === this.entity && view === this.view) {
                    return;
                }

                this.loadActions();
            },
        },

        extensionSdkButtonCount(count, previousCount) {
            // The SDK store only appends buttons. Ignore resets or removals, which do not require a server reload.
            if (count <= previousCount) {
                return;
            }

            // Vue can batch several registrations into one update. Check all additions so a matching button is not
            // missed when an unrelated button is registered afterwards in the same batch.
            const addedButtons = Shopware.Store.get('actionButtons').buttons.slice(previousCount, count);
            const hasButtonForCurrentView = addedButtons.some((button) => {
                return button.entity === this.entity && button.view === this.view;
            });

            if (hasButtonForCurrentView) {
                this.loadActions();
            }
        },
    },

    methods: {
        async runAction(action) {
            const entityIdList = { ids: this.params };

            if (hasOwnProperty(action, 'callback') && typeof action.callback === 'function') {
                action.callback(action.entity, entityIdList.ids);

                return;
            }

            const { data } = await this.appActionButtonService.runAction(action.id, entityIdList);
            const { actionType, redirectUrl, status, message } = data;

            this.action = this.actions.find((actionsAction) => {
                return actionsAction.id === action.id;
            });

            switch (actionType) {
                case actionTypeConstants.ACTION_OPEN_NEW_TAB:
                    window.open(redirectUrl, '_blank');
                    break;
                case actionTypeConstants.ACTION_SHOW_NOTIFICATION:
                    this.createNotification({
                        variant: status,
                        message: message,
                    });
                    break;
                case actionTypeConstants.ACTION_RELOAD_DATA:
                    this._reloadPage();
                    break;
                case actionTypeConstants.ACTION_OPEN_MODAL:
                    await this.getUserConfig();
                    this.size = modalSizeMapping[data.size] || 'default';
                    this.iframeUrl = data.iframeUrl;
                    this.isExpanded = data.expand === true;
                    this.isOpenModal = true;

                    break;
                default:
                    break;
            }
        },

        async loadActions() {
            try {
                this.actions = await this.appActionButtonService.getActionButtonsPerView(this.entity, this.view);
                this.actions.push(...this.extensionSdkButtons);
            } catch (e) {
                this.actions = [];

                // ignore missing parameter exception for pages without correct view
                if (!!e.name && e.name === 'InvalidActionButtonParameterError') {
                    return;
                }

                this.createNotificationError({
                    message: this.$t('sw-app.component.sw-app-actions.messageErrorFetchButtons'),
                });
            }
        },

        onCloseModal() {
            if (this.size === modalSizeMapping.small && !this.isExpanded) {
                this.isOpenModal = false;
            } else {
                this.onOpenModalConfirm();
            }
        },

        onOpenModalConfirm() {
            if (this.iframeUserConfig.value.isShowModalConfirm) {
                this.isOpenConfirmModal = true;
                return;
            }

            this.isOpenModal = false;
        },

        onCloseModalConfirm() {
            this.isOpenConfirmModal = false;
        },

        async onConfirmClose() {
            this.saveConfig(this.isShowModalConfirm);

            await this.onCloseModalConfirm();
            this.isOpenModal = false;
        },

        onChangeCheckboxShow() {
            this.isShowModalConfirm = !this.isShowModalConfirm;
        },

        getUserConfig() {
            this.userConfigRepository.search(this.userConfigCriteria, Shopware.Context.api).then((response) => {
                if (response.length) {
                    this.iframeUserConfig = response.first();
                } else {
                    this.iframeUserConfig = this.userConfigRepository.create(Shopware.Context.api);
                    this.iframeUserConfig.key = IFRAME_KEY;
                    this.iframeUserConfig.userId = this.currentUser?.id;
                    this.iframeUserConfig.value = {
                        isShowModalConfirm: true,
                    };
                }
            });
        },

        /** Thin wrapper so tests can spy on navigation without mocking window.location (non-configurable in JSDOM v26). */
        _reloadPage() {
            window.location.reload();
        },

        saveConfig(value) {
            this.iframeUserConfig.value = {
                isShowModalConfirm: value,
            };

            this.userConfigRepository.save(this.iframeUserConfig, Shopware.Context.api).then(() => {
                this.getUserConfig();
            });
        },
    },
};
