import template from './sw-event-action-deprecated-alert.html.twig';
import './sw-event-action-deprecated-alert.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

/**
 * @major-deprecated (flag:FEATURE_NEXT_8225) - will be removed. Please use `sw-flow` - Flow builder instead.
 */
Component.register('sw-event-action-deprecated-alert', {
    template,

    inject: [
        'repositoryFactory',
    ],

    data() {
        return {
            showAlert: false,
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
            const configurationKey = 'deprecatedAlert.businessEvent';
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
                    this.showAlert = true;
                    return;
                }
                this.currentSetting = response[0];
            });
        },

        createUserSetting() {
            const newDeprecatedAlert = this.userConfigRepository.create();
            newDeprecatedAlert.key = 'deprecatedAlert.businessEvent';
            newDeprecatedAlert.userId = this.currentUser?.id;
            this.currentSetting = newDeprecatedAlert;
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

        dismissModal() {
            this.saveUserSettings();
            this.showAlert = !this.showAlert;
        },
    },
});
