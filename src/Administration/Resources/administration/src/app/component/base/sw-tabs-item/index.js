import { Component } from 'src/core/shopware';

import template from './sw-tabs-item.html.twig';
import './sw-tabs-item.less';

/**
 * @private
 */
Component.register('sw-tabs-item', {
    template,

    props: {
        route: {
            type: [Object, String],
            required: false,
            default: ''
        },
        variant: {
            type: String,
            required: false,
            default: 'default',
            validValues: ['default', 'minimal'],
            validator(value) {
                if (!value.length) {
                    return true;
                }
                return ['default', 'minimal'].includes(value);
            }
        }
    },

    data() {
        return {
            tabsItemClass: {
                [`sw-tabs-item__${this.variant}`]: this.variant
            }
        };
    }
});
