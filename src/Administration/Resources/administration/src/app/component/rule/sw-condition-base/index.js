import template from './sw-condition-base.html.twig';
import './sw-condition-base.scss';

const { Component, Mixin, StateDeprecated } = Shopware;
const utils = Shopware.Utils;

/**
 * @public
 * @description Base condition for the condition-tree. This component must be a child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-base :condition="condition" :level="0"></sw-condition-base>
 */
Component.register('sw-condition-base', {
    template,

    inject: ['config', 'conditionStore', 'isApi'],

    mixins: [
        Mixin.getByName('validation'),
        Mixin.getByName('notification')
    ],

    props: {
        condition: {
            type: Object,
            required: false,
            default: null
        },
        level: {
            type: Number,
            required: true
        },
        parentDisabledDelete: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    data() {
        return {
            // TODO: Error Handling must be fixed NEXT-3271
            // formErrors: {},
            hasErrors: false,
            conditionTreeComponent: null
        };
    },

    computed: {
        fieldNames() {
            return [];
        },
        conditionClass() {
            return '';
        },
        defaultValues() {
            return {};
        },
        errorStore() {
            return StateDeprecated.getStore('error');
        },
        isApiCLass() {
            return this.isApi() ? 'is--api' : '';
        },
        hasErrorsClass() {
            return this.hasErrors ? 'has--error' : '';
        },
        currentLocale() {
            return this.$store.state.adminLocale.currentLocale;
        }
    },

    watch: {
        currentLocale() {
            this.translateConditions();
        }
    },

    beforeDestroy() {
        this.beforeDestroyComponent();
    },

    created() {
        this.createdComponent();
    },

    beforeMount() {
        this.beforeMountComponent();
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        createId() {
            return utils.createId();
        },

        checkErrors() {
            // TODO: Error Handling must be fixed NEXT-3271
            // const values = Object.values(this.formErrors);
            // this.hasErrors = (values.length && values.filter(error => error.detail.length > 0).length)
            //     || this.condition.errors.map(obj => obj.id).includes('clientValidationError');
            this.hasErrors = this.condition.errors.map(obj => obj.id).includes('clientValidationError');
        },

        beforeMountComponent() {
            this.applyDefaultValues();
        },

        mountedComponent() {
            if (!this.condition.value) {
                this.condition.value = {};
            }

            Object.keys(this.condition.value).forEach((key) => {
                if (!this.fieldNames.includes(key)) {
                    delete this.condition.value[key];
                }
            });

            const keys = Object.keys(this.condition.value);
            this.fieldNames.forEach((fieldName) => {
                if (!keys.includes(fieldName)) {
                    this.condition.value[fieldName] = undefined;
                }
            });

            const fieldNames = this.fieldNames;
            fieldNames.push('type');

            // TODO: Error Handling must be fixed NEXT-3271
            // fieldNames.forEach(fieldName => {
            //     const boundExpression =
            //         `${this.config.entityName}.${this.config.conditionIdentifier}.${this.condition.id}.${fieldName}`;
            //     this.formErrors[fieldName] = this.errorStore.registerFormField(boundExpression);
            // });

            this.$children.forEach(child => {
                if (!this.fieldNames.includes(child.$attrs.name)) {
                    return;
                }

                // TODO: Error Handling must be fixed NEXT-3271
                // child.$on('input', () => { this.deleteError(child.$attrs.name); });
                child.$on('input', () => { this.deleteError(); });
                child.$on('change', () => { this.deleteError(); });
                child.$on('input-change', () => { this.deleteError(); });
            });

            this.deleteError('type');
        },

        beforeDestroyComponent() {
            this.conditionTreeComponent.$off('entity-save', this.checkErrors);
        },

        // TODO: Error Handling must be fixed NEXT-3271
        // deleteError(fieldName) {
        //     if (!this.formErrors[fieldName].detail
        //         && (!this.condition.errors
        //             || this.condition.errors.length === 0
        //             || !this.condition.errors.map(obj => obj.id).includes('clientValidationError'))) {
        //         return;
        //     }
        //
        //     this.condition.errors = this.condition.errors.filter((error) => {
        //         return error.id !== 'clientValidationError';
        //     });
        //
        //     if (this.formErrors[fieldName].detail) {
        //         this.errorStore.deleteError(this.formErrors[fieldName]);
        //     }
        //
        //     this.checkErrors();
        // },

        deleteError() {
            if (!this.condition.errors
                || this.condition.errors.length === 0
                || !this.condition.errors.map(obj => obj.id).includes('clientValidationError')) {
                return;
            }

            this.condition.errors = this.condition.errors.filter((error) => {
                return error.id !== 'clientValidationError';
            });

            this.checkErrors();
        },

        getLabel(type) {
            const condition = this.conditionStore.getById(type);
            if (!condition) {
                return 'global.sw-condition.condition.not-found.label';
            }

            return condition.label;
        },

        createdComponent() {
            if (!this.condition.value) {
                this.condition.value = {};
            }

            this.locateConditionTreeComponent();

            this.addClientFieldValidationMethod();
            this.conditionTreeComponent.$on('entity-save', this.checkErrors);
        },

        addClientFieldValidationMethod() {
            this.config.dataCheckMethods[this.condition.type] = (condition) => {
                let dataSet = true;
                this.fieldNames.forEach((fieldName) => {
                    if (fieldName === 'type') {
                        return;
                    }

                    if (typeof condition.value[fieldName] === 'undefined'
                        || condition.value[fieldName] === null
                        || (condition.value[fieldName] instanceof Array
                            && condition.value[fieldName].length === 0)) {
                        dataSet = false;
                    }
                });

                return dataSet;
            };
        },

        locateConditionTreeComponent() {
            let parent = this.$parent;

            while (parent) {
                if (parent.$options.name === 'sw-condition-tree') {
                    this.conditionTreeComponent = parent;
                    return;
                }

                parent = parent.$parent;
            }

            throw new Error('component \'sw-condition-tree\' not found');
        },

        applyDefaultValues() {
            Object.keys(this.defaultValues).forEach(key => {
                if (typeof this.condition.value[key] === 'undefined') {
                    this.condition.value[key] = this.defaultValues[key];
                }
            });
        },

        deleteCondition() {
            this.$emit('condition-delete', this.condition);
        },

        conditionChanged(value) {
            if (value) {
                return;
            }

            this.$emit('condition-delete', this.condition);
        },

        translateConditions() {
            this.conditionStore.getList({}).then((conditions) => {
                conditions.items.forEach(condition => {
                    condition.translated = {
                        label: this.$tc(condition.label),
                        type: this.$tc(condition.label)
                    };
                });
            });
        }
    }
});
