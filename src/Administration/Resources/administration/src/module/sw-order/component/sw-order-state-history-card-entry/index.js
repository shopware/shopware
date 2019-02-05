import { Component } from 'src/core/shopware';
import './sw-order-state-history-card-entry.scss';
import template from './sw-order-state-history-card-entry.html.twig';

Component.register('sw-order-state-card-entry', {
    template,
    props: {
        entries: {
            type: Array,
            required: true,
            default() {
                return [];
            }
        },
        title: {
            type: String,
            required: false
        }
    },
    data() {
        return {
            iconMap: {
                'order.state': {
                    open: 'small-arrow-medium-down',
                    in_progress: 'small-default-circle-small',
                    cancelled: 'small-default-x-line-small',
                    completed: 'small-default-checkmark-line-small'
                },
                'order_transaction.state': {
                    open: 'small-arrow-medium-down',
                    paid: 'small-default-checkmark-line-small',
                    paid_partially: 'small-default-circle-small',
                    refunded: 'small-default-circle-small',
                    refunded_partially: 'small-default-circle-small',
                    reminded: 'small-default-circle-small',
                    cancelled: 'small-default-x-line-small'
                }
            },
            styleMap: {
                'order.state': {
                    open: 'sw-order-state-card__neutral',
                    in_progress: 'sw-order-state-card__progress',
                    cancelled: 'sw-order-state-card__danger',
                    completed: 'sw-order-state-card__success'
                },
                'order_transaction.state': {
                    open: 'sw-order-state-card__neutral',
                    paid: 'sw-order-state-card__success',
                    paid_partially: 'sw-order-state-card__progress',
                    refunded: 'sw-order-state-card__progress',
                    refunded_partially: 'sw-order-state-card__progress',
                    reminded: 'sw-order-state-card__progress',
                    cancelled: 'sw-order-state-card__danger'
                }
            }
        };
    },
    methods: {
        userDisplayName(user) {
            if (user === null) {
                return this.$tc('sw-order.stateCard.labelSystemUser');
            }

            return user.username;
        },
        getIconFromState(stateMachine, stateName) {
            if (stateMachine in this.iconMap && stateName in this.iconMap[stateMachine]) {
                return this.iconMap[stateMachine][stateName];
            }

            return 'small-arrow-medium-down';
        },
        getLabelColorFromState(stateMachine, stateName) {
            if (stateMachine in this.styleMap && stateName in this.styleMap[stateMachine]) {
                return `${this.styleMap[stateMachine][stateName]}-label`;
            }

            return 'sw-order-state-card__neutral-label';
        },
        getIconColorFromState(stateMachine, stateName) {
            if (stateMachine in this.styleMap && stateName in this.styleMap[stateMachine]) {
                return `${this.styleMap[stateMachine][stateName]}-icon`;
            }

            return 'sw-order-state-card__neutral-icon';
        }
    }
});
