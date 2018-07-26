import { Component } from 'src/core/shopware';
import template from './sw-search-bar-item.html.twig';
import './sw-search-bar-item.less';

Component.register('sw-search-bar-item', {
    template,

    props: {
        item: null
    }
});
