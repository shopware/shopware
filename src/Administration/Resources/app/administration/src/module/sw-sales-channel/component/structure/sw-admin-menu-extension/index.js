import template from './sw-admin-menu-extension.html.twig';

const { Component } = Shopware;

Component.override('sw-admin-menu', {
    template
});
