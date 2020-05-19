import template from './sw-entity-single-select-pagination.html.twig';
import './sw-entity-single-select-pagination.scss';

const { Component } = Shopware;

Component.extend('sw-entity-single-select-pagination', 'sw-pagination', {
    template
});
