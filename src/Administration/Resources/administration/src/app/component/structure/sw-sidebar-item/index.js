import { Component } from 'src/core/shopware';
import template from './sw-sidebar-item.html.twig';

Component.register('sw-sidebar-item', {
    props: ['entry'],
    template,
    methods: {
        getIconName(name) {
            return `icon-${name}`;
        }
    }
});
