import { storiesOf } from '@storybook/vue';
import { withKnobs, text, number } from '@storybook/addon-knobs/vue';
import vueComponents from '../helper/components.collector';
import SwagVueInfoAddon from '../addons/info-addon';

import description from './description.md';

storiesOf('sw-card', module)
    .addDecorator(SwagVueInfoAddon)
    .addDecorator(withKnobs)
    .add('Basic usage', () => ({
        description,
        components: {
            'sw-card': vueComponents.get('sw-card')
        },
        template: `
            <div>
                <sw-card title="Basic example" style="width: 400px; margin: 20px auto 0;">
                    Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut 
                    labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et
                    earebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum
                    dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore
                    magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet 
                    clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.
                </sw-card>
                <sw-card title="Another example" style="width: 400px; margin: 20px auto 0;">
                    Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut 
                    labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et
                    earebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum
                    dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore
                    magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet 
                    clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.
                </sw-card>
            </div>
        `
    }))
    .add('Card without title', () => ({
        components: {
            'sw-card': vueComponents.get('sw-card')
        },
        template: `
            <sw-card title="" style="width: 600px; margin: 20px auto 0;">
            Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut
            labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et
            earebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum
            dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore
            magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet 
            clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.
            </sw-card>
        `
    }))
    .add('Interactive Card', () => ({
        components: {
            'sw-card': vueComponents.get('sw-card')
        },
        data() {
            return {
                title: text('Card title', 'Example title'),
                width: number('Card width', 450, {
                    range: true,
                    min: 300,
                    max: 600,
                    step: 10
                }),
                desc: text(
                    'Card description',
                    'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut ' +
                    'labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores ' +
                    'et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. ' +
                    'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut ' +
                    'labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores ' +
                    'et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.'
                )
            };
        },
        template: `
            <sw-card :title="title" :style="{ width: width + 'px', 'margin': '20px auto 0' }">{{desc}}</sw-card>
        `
    }));
