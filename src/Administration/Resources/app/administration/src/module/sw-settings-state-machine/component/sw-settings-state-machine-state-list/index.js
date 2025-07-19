import template from './sw-settings-state-machine-state-list.html.twig';

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

    props: {
        stateMachineId: {
            type: String,
            required: true,
        },
    },

    data() {
        return {
            stateMachineStates: [],
            isLoading: false,
            isSaveSuccessful: false,
            currentStateMachineState: null,
        };
    },

    computed: {
        stateMachineStateRepository() {
            return this.repositoryFactory.create('state_machine_state');
        },

        stateMachineStateCriteria() {
            const criteria = new Criteria();

            criteria.addFilter(Criteria.equals('stateMachineId', this.stateMachineId));

            return criteria;
        },

        stateMachineStateColumns() {
            return [
                {
                    property: 'name',
                    dataIndex: 'name',
                    label: this.$tc('sw-settings-state-machine.state.list.grid.columnName'),
                    width: '50%',
                    inlineEdit: 'string',
                },
                {
                    property: 'technicalName',
                    dataIndex: 'technicalName',
                    label: this.$tc('sw-settings-state-machine.state.list.grid.columnTechnicalName'),
                    width: '50%',
                },
            ];
        },
    },

    watch: {
        stateMachineId() {
            this.loadStateMachineStates();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.loadStateMachineStates();
        },

        async loadStateMachineStates() {
            this.isLoading = true;

            try {
                this.stateMachineStates = await this.stateMachineStateRepository.search(this.stateMachineStateCriteria);
            } catch (error) {
                this.createNotificationError({
                    message: this.$tc(error.message),
                });
            } finally {
                this.isLoading = false;
            }
        },

        onInlineEditCancel() {
            this.loadStateMachineStates();
        },

        async onInlineEditSave(stateMachineState) {
            this.isLoading = true;

            try {
                await this.stateMachineStateRepository.save(stateMachineState);

                this.createNotificationSuccess({
                    title: this.$tc('global.default.success'),
                    message: this.$tc('sw-settings-state-machine.state.notification.successMessage'),
                });

                this.loadStateMachineStates();
            } catch {
                this.createNotificationError({
                    title: this.$tc('global.default.error'),
                    message: this.$tc('sw-settings-state-machine.state.notification.errorMessage'),
                });
            } finally {
                this.isLoading = false;
            }
        },

        showModal(stateMachineState) {
            this.currentStateMachineState = stateMachineState;
        },

        onModalClose() {
            this.currentStateMachineState = null;

            this.loadStateMachineStates();
        },
    },
});
