import { Component } from 'src/core/shopware';
import template from './swag-dashboard-extension.html.twig';
import './swag-dashbaoard-extension.less';

Component.override('sw-dashboard-index', {
    template,

    created() {
        console.log('Hello world');
    }
});
