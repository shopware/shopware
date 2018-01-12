/* global require */
import { configure } from '@storybook/vue';

/** Import global styles */
import 'src/app/assets/less/all.less';

function loadStories() {
    // You can require as many stories as you need.
    require('../stories/button');
    require('../stories/card');
    require('../stories/loader');
}

configure(loadStories, module);
