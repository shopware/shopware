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
                    label: 'Kleiner Gleich'
                },
                equals: {
                    identifier: '==',
                    label: 'Gleich'
                },
                greaterThanEquals: {
                    identifier: '>=',
                    label: 'Größer Gleich'
                },
                lowerThan: {
                    identifier: '<',
                    label: 'Kleiner'
                },
                greaterThan: {
                    identifier: '>',
                    label: 'Größer'
                },
                startsWith: {
                    identifier: '%*',
                    label: 'Beginnt mit'
                },
                endsWith: {
                    identifier: '*%',
                    label: 'Endet mit'
                },
                contains: {
                    identifier: '*',
                    label: 'enthält'
                }
            }
        };
    },

    computed: {
        conditionTypes() {
            return [
                {
                    identifier: 'Shopware\\Core\\Checkout\\Cart\\Rule\\BadsPriceRule',
                    label: 'Cart Amount Rule',
                    operatorSet: this.operatorSets
                },
                {
                    identifier: 'Shopware\\Core\\Checkout\\Cart\\Rule\\BadsPriceRule',
                    label: 'Goods Count Rule',
                    operatorSet: this.operatorSets
                },
                {
                    identifier: 'Shopware\\Core\\Checkout\\Cart\\Rule\\GoodsPriceRule',
                    label: 'Goods Price Rule',
                    operatorSet: this.operatorSets
                },
                {
                    identifier: 'Shopware\\Core\\Checkout\\Cart\\Rule\\BadsPriceRule',
                    label: 'Line Item of Type Rule',
                    operatorSet: this.operatorSets
                },
                {
                    identifier: 'Shopware\\Core\\Framework\\Rule\\DateRangeRule',
                    label: 'Date Range Rule',
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
