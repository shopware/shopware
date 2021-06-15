import template from './sw-price-preview.html.twig';

const { Component } = Shopware;

Component.extend('sw-price-preview', 'sw-price-field', {
    template,
});
