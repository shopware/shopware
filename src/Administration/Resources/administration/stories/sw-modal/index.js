import { storiesOf } from '@storybook/vue';
import {boolean, withKnobs} from '@storybook/addon-knobs/vue';

import SwagVueInfoPanel from '../addons/info-addon';
import vueComponents from '../helper/components.collector';

import description from './description.md';

storiesOf('sw-modal', module)
    .addDecorator(SwagVueInfoPanel)
    .addDecorator(withKnobs)
    .add('Basic usage', () => ({
        description,
        components: {
            'sw-modal': vueComponents.get('sw-modal')
        },
        data() {
            return {
                isModalOpened: boolean('Modal opened?', false)
            };
        },
        template: `
            <div>
                <!-- Modal trigger -->
                <sw-button @click.prevent="isModalOpened = !isModalOpened">Open Modal</sw-button>

                <!-- Modal template -->
                <sw-modal @closeModal="isModalOpened = false" 
                          v-if="isModalOpened" 
                          modalTitle="Modal title">
                    Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt
                    ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo 
                    duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum 
                    dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy 
                    eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero
                    eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata 
                    sanctus est Lorem ipsum dolor sit amet.
                    <template slot="modal-footer">
                        <sw-button size="small" @click.prevent="isModalOpened = false">Close</sw-button>
                        <sw-button variant="primary" size="small" @click.prevent="isModalOpened = false">Okay</sw-button>
                    </template>
                </sw-modal>
            </div>`
    }))
    .add('Sizes', () => ({
        components: {
            'sw-modal': vueComponents.get('sw-modal')
        },
        data() {
            return {
                isDefaultModalOpened: boolean('Default modal opened?', false),
                isLargeModalOpened: boolean('Large modal opened?', false),
                isSmallModalOpened: boolean('Small modal opened?', false),
                isFullModalOpened: boolean('Full size modal opened?', false),
            };
        },
        template: `
            <div>
                <!-- Modal trigger -->
                <sw-button @click.prevent="isDefaultModalOpened = !isDefaultModalOpened">Default modal</sw-button>
                <sw-button @click.prevent="isSmallModalOpened = !isSmallModalOpened">Small modal</sw-button>
                <sw-button @click.prevent="isLargeModalOpened = !isLargeModalOpened">Large modal</sw-button>
                <sw-button @click.prevent="isFullModalOpened = !isFullModalOpened">Full size modal</sw-button>

                <!-- Default modal -->
                <sw-modal @closeModal="isDefaultModalOpened = false" 
                          v-if="isDefaultModalOpened" 
                          modalTitle="Default modal title">
                    Default modal Content
                    <template slot="modal-footer">
                        <sw-button size="small" @click.prevent="isDefaultModalOpened = false">Close</sw-button>
                        <sw-button variant="primary" size="small" @click.prevent="isDefaultModalOpened = false">Okay</sw-button>
                    </template>
                </sw-modal>
                
                <!-- Small modal -->
                <sw-modal @closeModal="isSmallModalOpened = false" 
                          v-if="isSmallModalOpened"
                          size="small" 
                          modalTitle="Large modal title">
                    Large modal Content
                    <template slot="modal-footer">
                        <sw-button size="small" @click.prevent="isSmallModalOpened = false">Close</sw-button>
                        <sw-button variant="primary" size="small" @click.prevent="isSmallModalOpened = false">Okay</sw-button>
                    </template>
                </sw-modal>

                <!-- Large modal -->
                <sw-modal @closeModal="isLargeModalOpened = false" 
                          v-if="isLargeModalOpened"
                          size="large" 
                          modalTitle="Large modal title">
                    Large modal Content
                    <template slot="modal-footer">
                        <sw-button size="small" @click.prevent="isLargeModalOpened = false">Close</sw-button>
                        <sw-button variant="primary" size="small" @click.prevent="isLargeModalOpened = false">Okay</sw-button>
                    </template>
                </sw-modal>
                
                <!-- Full size modal -->
                <sw-modal @closeModal="isFullModalOpened = false" 
                          v-if="isFullModalOpened"
                          size="full" 
                          modalTitle="Large modal title">
                    Large modal Content
                    <template slot="modal-footer">
                        <sw-button size="small" @click.prevent="isFullModalOpened = false">Close</sw-button>
                        <sw-button variant="primary" size="small" @click.prevent="isFullModalOpened = false">Okay</sw-button>
                    </template>
                </sw-modal>
            </div>`
    }));
