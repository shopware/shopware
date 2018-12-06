import { Component, Mixin } from 'src/core/shopware';
import template from './sw-condition-group.html.twig';
import './sw-condition-group.less';

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
            default: () => {
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
                }
            }
        };
    },

    computed: {
        conditionTypes() {
            return [
                {
                    identifier: 'Shopware\\Core\\Checkout\\Cart\\Rule\\BadsPriceRule',
                    label: this.$tc('global.sw-condition-group.coniditon.cartAmountRule'),
                    operatorSet: this.operatorSets
                },
                {
                    identifier: 'Shopware\\Core\\Checkout\\Cart\\Rule\\BadsPriceRule',
                    label: this.$tc('global.sw-condition-group.coniditon.goodsCountRule'),
                    operatorSet: this.operatorSets
                },
                {
                    identifier: 'Shopware\\Core\\Checkout\\Cart\\Rule\\GoodsPriceRule',
                    label: this.$tc('global.sw-condition-group.coniditon.goodsPriceRule'),
                    operatorSet: this.operatorSets
                },
                {
                    identifier: 'Shopware\\Core\\Checkout\\Cart\\Rule\\BadsPriceRule',
                    label: this.$tc('global.sw-condition-group.coniditon.lineItemOfTypeRule'),
                    operatorSet: this.operatorSets
                },
                {
                    identifier: 'Shopware\\Core\\Framework\\Rule\\DateRangeRule',
                    label: this.$tc('global.sw-condition-group.coniditon.dateRangeRule'),
                    operatorSet: this.operatorSets
                }
            ];
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
                    this.operators
                ]
            };
        }
    },

    watch: {
        value(value) {
            this.currentConditionType = this.convertValueType(value);
        }
    },

    created() {},

    mounted() {
        this.componentMounted();
    },

    methods: {
        componentMounted() {
            this.conditionTypeSelect = this.$refs.conditionTypeSelect;
        }
    }
});
