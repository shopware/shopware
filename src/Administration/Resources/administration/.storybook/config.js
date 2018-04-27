/* global require */
import { configure } from '@storybook/vue';
import Vue from 'vue';
import VueI18n from 'vue-i18n'

Vue.use(VueI18n);

/** Import global styles */
import 'src/app/assets/less/all.less';

function loadStories() {
    // You can require as many stories as you need.
    require('../stories/sw-alert/index');
    require('../stories/sw-avatar/index');
    require('../stories/sw-button/index');
    require('../stories/sw-card/index');
    require('../stories/sw-color-badge/index');
    require('../stories/sw-context-button/index');
    require('../stories/sw-field/index');
    require('../stories/sw-grid/index');
    require('../stories/sw-icon/index');
    require('../stories/sw-loader/index');
    require('../stories/sw-pagination/index');
    require('../stories/sw-sidebar/index');
    require('../stories/sw-modal/index');
    require('../stories/sw-address/index');
}

configure(loadStories, module);
