/**
 * @sw-package framework
 *
 * Mounts the page that `<body data-page>` names: the overview or one direction inside the review shell.
 */
import '@shopware-ag/meteor-component-library/styles.css';
import '@shopware-ag/meteor-component-library/font.css';
import './shared/base.css';
import { DeviceHelperPlugin } from '@shopware-ag/meteor-component-library';
import { createApp, h } from 'vue';
import type { Component } from 'vue';
import { createI18n } from 'vue-i18n';
import Overview from './design-overview.vue';
import { DIRECTIONS } from './directions';
import { ONE_FIELD_LAYOUTS } from './one-field-layouts';
import DesignShell from './shared/design-shell.vue';

const page = document.body.dataset.page ?? 'overview';
const direction = [
    ...DIRECTIONS,
    ...ONE_FIELD_LAYOUTS,
].find(({ id }) => id === page);
const root: Component = direction === undefined ? Overview : { render: () => h(DesignShell, { direction }) };

createApp(root)
    .use(createI18n({ legacy: false, locale: 'en', fallbackLocale: 'en' }))
    // The tabs of Meteor watch the width of the window through it.
    .use(DeviceHelperPlugin)
    .mount('#app');
