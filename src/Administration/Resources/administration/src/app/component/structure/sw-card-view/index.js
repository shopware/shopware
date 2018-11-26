import { Component } from 'src/core/shopware';
import template from './sw-card-view.html.twig';
import './sw-card-view.less';

/**
 * @public
 * @description
 * Container for the <sw-card> component.
 * @status ready
 * @example-type static
 * @component-example
 * <sw-card-view style="height: 400px;">
 *    <sw-card title="Card-1" large>
 *        Lorem ipsum dolor sit amet
 *    </sw-card>
 *    <sw-card title="Card-2" large>
 *        Lorem ipsum dolor sit amet
 *    </sw-card>
 * </sw-card-view>
 */
Component.register('sw-card-view', {
    template,

    props: {
        sidebar: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    computed: {
        cardViewClasses() {
            return {
                'has--sidebar': this.sidebar
            };
        }
    }
});
