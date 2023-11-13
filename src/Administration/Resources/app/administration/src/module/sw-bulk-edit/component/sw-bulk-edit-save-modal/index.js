/**
 * @package system-settings
 */
import template from './sw-bulk-edit-save-modal.html.twig';
import './sw-bulk-edit-save-modal.scss';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    props: {
        itemTotal: {
            required: true,
            type: Number,
        },
        isLoading: {
            required: true,
            type: Boolean,
        },
        processStatus: {
            required: true,
            type: String,
        },
        /**
        * {
        *     ...
        *     orderDeliveries: {
        *         isChanged: true,
        *         type: 'overwrite',
        *         value: 'cancel'
        *     },
        *     orderTransactions: {
        *         isChanged: true,
        *         type: 'overwrite',
        *         value: 'cancel'
        *     },
        *     orders: {
        *         isChanged: true,
        *         type: 'overwrite',
        *         value: 'cancel'
        *     }
        *     ...
        * }
        */
        bulkEditData: {
            type: Object,
            required: false,
            default: () => {
                return {};
            },
        },
    },

    data() {
        return {
            title: null,
            buttonConfig: [],
        };
    },

    computed: {
        currentStep() {
            if (this.isLoading && !this.processStatus) {
                return 'process';
            }

            if (!this.isLoading && this.processStatus === 'success') {
                return 'success';
            }

            if (!this.isLoading && this.processStatus === 'fail') {
                return 'fail';
            }

            return 'confirm';
        },

        buttons() {
            return {
                right: this.buttonConfig.filter((button) => button.position === 'right'),
                left: this.buttonConfig.filter((button) => button.position === 'left'),
            };
        },
    },

    watch: {
        currentStep(value) {
            if (value === 'success') {
                this.redirect('success');
            }

            if (value === 'fail') {
                this.redirect('error');
            }
        },
    },

    created() {
        this.createdComponent();
    },

    beforeDestroy() {
        this.beforeDestroyComponent();
    },

    methods: {
        createdComponent() {
            this.addEventListeners();
        },

        beforeDestroyComponent() {
            this.removeEventListeners();
        },

        addEventListeners() {
            window.addEventListener('beforeunload', (event) => this.beforeUnloadListener(event));
        },

        removeEventListeners() {
            window.removeEventListener('beforeunload', (event) => this.beforeUnloadListener(event));
        },

        beforeUnloadListener(event) {
            if (!this.isLoading) {
                return '';
            }

            event.preventDefault();
            event.returnValue = this.$tc('sw-bulk-edit.modal.messageBeforeTabLeave');

            return this.$tc('sw-bulk-edit.modal.messageBeforeTabLeave');
        },

        onModalClose() {
            this.$emit('modal-close');
        },

        applyChanges() {
            this.$emit('bulk-save');
        },

        redirect(routeName) {
            if (!routeName) {
                this.$emit('modal-close');
                return;
            }

            this.$router.push({ path: routeName });
        },

        setTitle(title) {
            this.title = title;
        },

        updateButtons(buttonConfig) {
            this.buttonConfig = buttonConfig;
        },

        onButtonClick(action) {
            if (typeof action === 'string') {
                this.redirect(action);
                return;
            }

            if (typeof action !== 'function') {
                return;
            }

            action.call();
        },
    },
};
