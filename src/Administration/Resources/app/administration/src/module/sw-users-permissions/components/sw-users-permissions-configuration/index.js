/**
 * @package system-settings
 */
import template from './sw-users-permissions-configuration.html.twig';
import './sw-users-permissions-configuration.scss';

const { Component } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-users-permissions-configuration', {
    template,

    inject: ['acl'],

    methods: {
        onChangeLoading(loading) {
            this.$emit('loading-change', loading);
        },
    },
});
