import { Component, Mixin } from 'src/core/shopware';
import PaginationMixin from 'src/app/component/mixin/pagination.mixin';
import template from './sw-catalog-list.html.twig';
import './sw-catalog-list.less';

Component.register('sw-catalog-list', {
    template,

    mixins: [
        PaginationMixin,
        Mixin.getByName('catalogList')
    ]
});
