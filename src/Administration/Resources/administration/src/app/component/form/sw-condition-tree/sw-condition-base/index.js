import { Mixin, State } from 'src/core/shopware';
import utils from 'src/core/service/util.service';
import template from './sw-condition-base.html.twig';
import './sw-condition-base.scss';

/**
 * @public
 * @description Base condition for the condition-tree. This component must be a child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-base :condition="condition" :level="0"></sw-condition-base>
 */
export default {
    name: 'sw-condition-base',
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
            return State.getStore('error');
        }
    },

    beforeDestroy() {
        this.conditionTreeComponent.$off('on-save', this.checkErrors);
    },

    data() {
        return {
            formErrors: {},
            hasErrors: false,
            conditionTreeComponent: null
        };
    },

    created() {
        this.createdComponent();
    },
    beforeMount() {
        this.applyDefaultValues();
    },

    mounted() {
        this.mountComponent();
    },

    methods: {
        createId() {
            return utils.createId();
        },
        checkErrors() {
            const values = Object.values(this.formErrors);
            this.hasErrors = values.length && values.filter(error => error.detail.length > 0).length;
        },
        mountComponent() {
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

            fieldNames.forEach(fieldName => {
                const boundExpression =
                    `${this.config.entityName}.${this.config.conditionIdentifier}.${this.condition.id}.${fieldName}`;
                this.formErrors[fieldName] = this.errorStore.registerFormField(boundExpression);
            });

            this.$children.forEach(child => {
                if (!this.fieldNames.includes(child.$attrs.name)) {
                    return;
                }

                child.$on('input', () => { this.deleteError(child.$attrs.name); });
            });

            this.deleteError('type');
        },

        deleteError(fieldName) {
            if (!this.formErrors[fieldName].detail) {
                return;
            }

            this.errorStore.deleteError(this.formErrors[fieldName]);
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

            this.conditionTreeComponent.$on('on-save', this.checkErrors);
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
        conditionChanged(value) {
            if (value) {
                return;
            }

            this.$emit('delete-condition', this.condition);
        }
    }
};
