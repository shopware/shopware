import { Component } from 'src/core/shopware';
import template from './sw-settings-wrapper-item.html.twig';

import './sw-settings-wrapper-item.less';

Component.register('sw-settings-wrapper-item', {
    template,

    props: {
        label: {
            required: true,
            type: String
        },
        to: {
            required: true,
            type: Object,
            default() {
                return {};
            }
        }
    }
});
