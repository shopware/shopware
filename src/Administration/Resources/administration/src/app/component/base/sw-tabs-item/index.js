import { Component } from 'src/core/shopware';

import template from './sw-tabs-item.html.twig';
import './sw-tabs-item.less';

Component.register('sw-tabs-item', {
    template,

    props: {
        route: {
            type: [Object, String],
            required: false,
            default: ''
        }
    }
});
