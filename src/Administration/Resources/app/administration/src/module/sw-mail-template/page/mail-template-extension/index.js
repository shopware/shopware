import template from './mail-template-extension.html.twig';

const { Component } = Shopware;

Component.override('sw-mail-template-detail', {
    template,
});
