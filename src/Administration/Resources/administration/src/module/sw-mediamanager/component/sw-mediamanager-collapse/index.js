import { Component } from 'src/core/shopware';
import 'src/app/component/base/sw-collapse';
import template from './sw-mediamanager-collapse.html.twig';
import './sw-mediamanager-collapse.less';

Component.extend('sw-mediamanager-collapse', 'sw-collapse', {
    template,

    props: {
        title: {
            type: String,
            required: true
        }
    },

    computed: {
        expandButtonClass() {
            return {
                'is--hidden': this.expanded
            };
        },
        collapseButtonClass() {
            return {
                'is--hidden': !this.expanded
            };
        }
    }
});
