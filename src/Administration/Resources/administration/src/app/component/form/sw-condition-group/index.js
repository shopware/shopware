import { Component, Mixin } from 'src/core/shopware';
import template from './sw-condition-group.html.twig';
import './sw-condition-group.less';

/**
 * @public
 * @description Universal condition field group component which supports all basic conditions, operators and field value types.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-group :type="rule.type" :condition="condition"></sw-condition-group>
 */
Component.register('sw-condition-group', {
    template,

    mixins: [
        Mixin.getByName('validation'),
        Mixin.getByName('notification')
    ],

    /**
     * All additional passed attributes are bound explicit to the correct child element.
     */
    inheritAttrs: false,

    props: {
        type: {
            type: String,
            required: false,
            validValues: ['price', 'shipping', 'payment', 'product_stream', 'customer_stream', 'cms'],
            validator(value) {
                if (!value.length) {
                    return true;
                }

                return ['price', 'shipping', 'payment', 'product_stream', 'customer_stream', 'cms'].includes(value);
            },
            default: 'text'
        },
        label: {
            type: String,
            required: false,
            default: ''
        },
        condition: {
            type: Object,
            required: false,
            default() {
                return {};
            }
        },
        options: {
            type: Array,
            required: false,
            default: () => {
                return [];
            }
        }
    },

    data() {
        return {
            currentValue: null,
            boundExpression: '',
            operators: {
                lowerThanEquals: {
                    identifier: '<=',
                    label: this.$tc('global.sw-condition-group.operator.lowerThanEquals')
                },
                equals: {
                    identifier: '==',
                    label: this.$tc('global.sw-condition-group.operator.equals')
                },
                greaterThanEquals: {
                    identifier: '>=',
                    label: this.$tc('global.sw-condition-group.operator.greaterThanEquals')
                },
                lowerThan: {
                    identifier: '<',
                    label: this.$tc('global.sw-condition-group.operator.lower')
                },
                greaterThan: {
                    identifier: '>',
                    label: this.$tc('global.sw-condition-group.operator.greater')
                },
                startsWith: {
                    identifier: '%*',
                    label: this.$tc('global.sw-condition-group.operator.startsWidth')
                },
                endsWith: {
                    identifier: '*%',
                    label: this.$tc('global.sw-condition-group.operator.endsWidth')
                },
                contains: {
                    identifier: '*',
                    label: this.$tc('global.sw-condition-group.operator.contains')
                },
                regex: {
                    identifier: 'preg_match',
                    label: this.$tc('global.sw-condition-group.operator.regex')
                }
            },
            conditionOperators: {}
        };
    },

    computed: {
        conditionTypes() {
            return {
                'Shopware\\Core\\Checkout\\Cart\\Rule\\BadsPriceRule': {
                    label: this.$tc('global.sw-condition-group.condition.cartAmountRule'),
                    operatorSet: this.operatorSets.defaultSet
                },
                'Shopware\\Core\\Checkout\\Cart\\Rule\\GoodsPriceRule': {
                    label: this.$tc('global.sw-condition-group.condition.goodsPriceRule'),
                    operatorSet: this.operatorSets.defaultSet
                },
                'Shopware\\Core\\Checkout\\Cart\\Rule\\LineItemOfTypeRule': {
                    label: this.$tc('global.sw-condition-group.condition.lineItemOfTypeRule'),
                    operatorSet: this.operatorSets.all
                },
                'Shopware\\Core\\Framework\\Rule\\DateRangeRule': {
                    label: this.$tc('global.sw-condition-group.condition.dateRangeRule'),
                    operatorSet: this.operatorSets.test
                }
            };
        },
        operatorSets() {
            return {
                defaultSet: [
                    this.operators.equals,
                    this.operators.lowerThan,
                    this.operators.greaterThan,
                    this.operators.lowerThanEquals,
                    this.operators.greaterThanEquals
                ],
                all: [
                    // todo get dynmaic all operators
                    Object.values(this.operators)
                ],
                test: [
                    this.operators.equals,
                    this.operators.lowerThan
                ]
            };
        }
    },

    watch: {
        value(value) {
            this.currentConditionType = this.convertValueType(value);
        }
    },

    created() {
        this.createdComponent();
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        mountedComponent() {
            this.conditionTypeSelect = this.$refs.conditionTypeSelect;
        },

        createdComponent() {
            const conditionType = this.condition.type;
            const conditionTypes = this.conditionTypes;
            this.conditionOperators = conditionTypes[conditionType].operatorSet;
        },

        handleConditionChange(event) {
            this.conditionOperators = this.conditionTypes[event.target.value].operatorSet;
        },

        handleOperatorChange() {
            // todo
        }
    }
});
