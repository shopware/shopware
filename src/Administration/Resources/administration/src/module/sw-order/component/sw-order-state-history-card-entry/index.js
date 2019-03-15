import { Component } from 'src/core/shopware';
import './sw-order-state-history-card-entry.scss';
import template from './sw-order-state-history-card-entry.html.twig';

Component.register('sw-order-state-card-entry', {
    template,
    inject: ['stateStyleDataProviderService'],
    props: {
        history: {
            type: Array,
            required: true,
            default() {
                return [];
            }
        },
        transitionOptions: {
            type: Array,
            required: true,
            default() {
                return [];
            }
        },
        stateMachineName: {
            type: String,
            required: true,
            default: ''
        },
        title: {
            type: String,
            required: false
        }
    },
    created() {
        this.createdComponent();
    },
    methods: {
        createdComponent() {
        },
        userDisplayName(user) {
            let userString = '';
            if (user === null) {
                userString = this.$tc('sw-order.stateCard.labelSystemUser');
            } else {
                userString = user.username;
            }

            return `${this.$tc('sw-order.stateCard.labelLastEditedBy')} ${userString}`;
        },
        getIconFromState(stateName) {
            return this.stateStyleDataProviderService.getStyle(this.stateMachineName, stateName).icon;
        },
        getIconColorFromState(stateName) {
            return this.stateStyleDataProviderService.getStyle(this.stateMachineName, stateName).iconStyle;
        },
        getBackgroundColorFromState(stateName) {
            return this.stateStyleDataProviderService.getStyle(this.stateMachineName, stateName).iconBackgroundStyle;
        }
    }
});
