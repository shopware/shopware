import { storiesOf } from '@storybook/vue';
import { withKnobs } from '@storybook/addon-knobs/vue';

import SwagVueInfoPanel from '../addons/info-addon';
import vueComponents from '../helper/components.collector';

import description from './description.md';

storiesOf('sw-alert', module)
    .addDecorator(SwagVueInfoPanel)
    .addDecorator(withKnobs)
    .add('Basic usage', () => ({
        description,
        components: {
            'sw-alert': vueComponents.get('sw-alert')
        },
        template: `
            <div>
                <div style="float:left; width: 50%">
                    <sw-alert variant="info">
                        Lorem ipsum dolor sit amet, consectetur adipisicing elit. Blanditiis culpa, cupiditate earum eos.
                    </sw-alert>
                    <sw-alert variant="success">
                        Lorem ipsum dolor sit amet, consectetur adipisicing elit. Blanditiis culpa, cupiditate earum eos.
                    </sw-alert>
                    <sw-alert variant="warning">
                        Lorem ipsum dolor sit amet, consectetur adipisicing elit. Blanditiis culpa, cupiditate earum eos.
                    </sw-alert>
                    <sw-alert variant="error">
                        Lorem ipsum dolor sit amet, consectetur adipisicing elit. Blanditiis culpa, cupiditate earum eos.
                    </sw-alert>
                    <sw-alert>
                        Lorem ipsum dolor sit amet, consectetur adipisicing elit. Blanditiis culpa, cupiditate earum eos.
                    </sw-alert>
                </div>
                <div style="float:left; width: 50%">
                    <sw-alert variant="info" title="Info">
                        Lorem ipsum dolor sit amet, consectetur adipisicing elit. Blanditiis culpa, cupiditate earum eos.
                    </sw-alert>
                    <sw-alert variant="success" title="Success">
                        Lorem ipsum dolor sit amet, consectetur adipisicing elit. Blanditiis culpa, cupiditate earum eos.
                    </sw-alert>
                    <sw-alert variant="warning" title="Warning">
                        Lorem ipsum dolor sit amet, consectetur adipisicing elit. Blanditiis culpa, cupiditate earum eos.
                    </sw-alert>
                    <sw-alert variant="error" title="Error">
                        Lorem ipsum dolor sit amet, consectetur adipisicing elit. Blanditiis culpa, cupiditate earum eos.
                    </sw-alert>
                    <sw-alert title="Default">
                        Lorem ipsum dolor sit amet, consectetur adipisicing elit. Blanditiis culpa, cupiditate earum eos.
                    </sw-alert>
                </div>
            </div>`
    }));