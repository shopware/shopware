import { storiesOf } from '@storybook/vue';

import SwagVueInfoPanel from '../addons/info-addon';
import vueComponents from '../helper/components.collector';

import description from './description.md';

storiesOf('sw-tabs', module)
    .addDecorator(SwagVueInfoPanel)
    .add('Basic usage', () => ({
        description,
        components: {
            'sw-tabs': vueComponents.get('sw-tabs'),
            'sw-tabs-item': vueComponents.get('sw-tabs-item')
        },
        template: `
            <sw-tabs>
                <sw-tabs-item :route="#" label="Tab name 1">Tab name 1</sw-tabs-item>
                <sw-tabs-item :route="#" label="Tab name 1">Tab name 2</sw-tabs-item>
                <sw-tabs-item :route="#" label="Tab name 1">Tab name 3</sw-tabs-item>
            </sw-tabs>`
    }));
