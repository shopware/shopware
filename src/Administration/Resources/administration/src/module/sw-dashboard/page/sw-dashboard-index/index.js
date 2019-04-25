import { Component } from 'src/core/shopware';
import template from './sw-dashboard-index.html.twig';
import './sw-dashboard-index.scss';

Component.register('sw-dashboard-index', {
    template,

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    computed: {
        roadmapLink() {
            return this.$tc('sw-dashboard.welcome.roadmapLink');
        },

        username() {
            if (this.$store.state.adminUser.currentProfile) {
                return this.$store.state.adminUser.currentProfile.firstName;
            }
            return '';
        }
    }
});
