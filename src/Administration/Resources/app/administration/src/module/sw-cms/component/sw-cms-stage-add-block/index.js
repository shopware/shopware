import template from './sw-cms-stage-add-block.html.twig';
import './sw-cms-stage-add-block.scss';

const { Component } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-cms-stage-add-block', {
    template,
});
