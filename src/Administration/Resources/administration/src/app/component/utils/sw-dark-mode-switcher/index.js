import { mapActions, mapGetters } from 'vuex';
import template from './sw-dark-mode-switcher.html.twig';

const { Component } = Shopware;

/**
 * @public
 * @description
 * A switcher component which controls the dark mode state
 * @status ready
 * @example-type static
 * @component-example
 * <sw-dark-mode-switcher></sw-dark-mode-switcher>
 */
Component.register('sw-dark-mode-switcher', {
    template,

    computed: {
        ...mapGetters({
            darkMode: 'adminPreferedColorScheme/getMode'
        }),

        isDarkMode: {
            set(value) {
                this.setDarkMode(value ? 'dark' : 'light');
            },

            get() {
                return this.darkMode === 'dark';
            }
        }
    },

    methods: {
        ...mapActions({
            setDarkMode: 'adminPreferedColorScheme/setMode'
        })
    }
});
