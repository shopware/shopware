import template from './sw-event-action-deprecated-modal.html.twig';
import './sw-event-action-deprecated-modal.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

/**
 * @deprecated tag:v6.5.0 - Will be removed in v6.5.0. Please use `sw-flow` - Flow builder instead.
 */
Component.register('sw-event-action-deprecated-modal', {
    template,

    inject: [
        'repositoryFactory',
    ],

    data() {
        return {
            showModal: false,
            currentSetting: {},
        };
    },

    computed: {
        userConfigRepository() {
            return this.repositoryFactory.create('user_config');
        },

        currentUser() {
            return Shopware.State.get('session').currentUser;
        },

        userSettingCriteria() {
            const criteria = new Criteria();
            const configurationKey = 'deprecatedModal.businessEvent';
            criteria.addFilter(Criteria.equals('key', configurationKey));
            criteria.addFilter(Criteria.equals('userId', this.currentUser?.id));

            return criteria;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {

        createdComponent() {
            this.findUserSetting();
        },

        findUserSetting() {
            return this.userConfigRepository.search(this.userSettingCriteria).then((response) => {
                if (!response.length) {
                    this.showModal = true;
                    return;
                }
                this.currentSetting = response[0];
            });
        },

        createUserSetting() {
            const newDeprecatedModal = this.userConfigRepository.create();
            newDeprecatedModal.key = 'deprecatedModal.businessEvent';
            newDeprecatedModal.userId = this.currentUser?.id;
            this.currentSetting = newDeprecatedModal;
        },

        saveUserSettings() {
            if (!this.currentSetting.id) {
                this.createUserSetting();
            }

            this.currentSetting.value = {
                isClosed: true,
            };

            this.userConfigRepository.save(this.currentSetting);
        },

        closeModal() {
            this.showModal = !this.showModal;
            this.saveUserSettings();
        },

        redirectToFlowBuilder() {
            this.closeModal();
            this.$nextTick(() => {
                this.$router.replace({ name: 'sw.flow.index' });
            }, 0);
        },
    },
});
