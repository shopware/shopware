import { storiesOf } from '@storybook/vue';
import { withKnobs } from '@storybook/addon-knobs/vue';

import SwagVueInfoPanel from '../addons/info-addon';
import vueComponents from '../helper/components.collector';

import description from './description.md';

storiesOf('sw-icon', module)
    .addDecorator(SwagVueInfoPanel)
    .addDecorator(withKnobs)
    .add('Basic usage', () => ({
        description,
        components: {
            'sw-icon': vueComponents.get('sw-icon')
        },
        template: `
            <div>
                <sw-icon name="text-editor-code" color="#189EFF"></sw-icon>
                <sw-icon name="default-award-medal" :large="true" color="#DE294C"></sw-icon>
                <sw-icon name="default-basic-checkmark-line" :small="true" color="#FFB75D"></sw-icon>
                <sw-icon name="default-building-shop" color="#37D046" size="28px"></sw-icon>
                <sw-icon name="default-communication-speech-bubbles" color="#16325C" title="An icon"></sw-icon>
                <sw-icon name="default-device-laptop" color="#607182" :decorative="true"></sw-icon>
            </div>`
    }));
