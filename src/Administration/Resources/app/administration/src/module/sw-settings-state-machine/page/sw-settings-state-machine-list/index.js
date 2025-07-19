import template from './sw-settings-state-machine-list.html.twig';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

/**
 * @sw-package checkout
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default Component.wrapComponentConfig({
    template,

    compatConfig: Shopware.compatConfig,

    inject: [
        'repositoryFactory',
        'acl',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            stateMachines: [],
            isLoading: false,
            total: 0,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        stateMachineRepository() {
            return this.repositoryFactory.create('state_machine');
        },

        stateMachineColumns() {
            return [
                {
                    property: 'name',
                    dataIndex: 'name',
                    label: 'sw-settings-state-machine.list.grid.columnName',
                    width: '50%',
                    inlineEdit: 'string',
                    routerLink: 'sw.settings.state.machine.detail',
                },
                {
                    property: 'technicalName',
                    dataIndex: 'technicalName',
                    label: 'sw-settings-state-machine.list.grid.columnTechnicalName',
                    width: '50%',
                },
            ];
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.loadStateMachines();
        },

        async loadStateMachines() {
            this.isLoading = true;

            try {
                this.stateMachines = await this.stateMachineRepository.search(new Criteria());

                this.total = this.stateMachines.total;
            } finally {
                this.isLoading = false;
            }
        },

        onChangeLanguage() {
            this.loadStateMachines();
        },

        onInlineEditCancel() {
            this.loadStateMachines();
        },

        async onInlineEditSave(stateMachine) {
            this.isLoading = true;

            try {
                await this.stateMachineRepository.save(stateMachine);

                this.loadStateMachines();

                this.createNotificationSuccess({
                    title: this.$tc('global.default.success'),
                    message: this.$tc('sw-settings-state-machine.notification.successMessage'),
                });
            } catch {
                this.createNotificationError({
                    title: this.$tc('global.default.error'),
                    message: this.$tc('sw-settings-state-machine.notification.errorMessage'),
                });
            } finally {
                this.isLoading = false;
            }
        },
    },
});
