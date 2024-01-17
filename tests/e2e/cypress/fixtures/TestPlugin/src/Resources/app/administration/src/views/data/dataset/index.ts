import Vue from 'vue'
import { data } from '@shopware-ag/meteor-admin-sdk';
import { SwButton, SwTextField } from "@shopware-ag/meteor-component-library";


// make background color white
document.body.style.backgroundColor = 'white';

export default Vue.extend({
    template: `
<div>
    <sw-button @click="getDataset">Get dataset</sw-button>
    <sw-button @click="subscribeData">Subscribe dataset</sw-button>

    <br />
    <br />

    <p>Clicked on: {{ clicked }}</p>
    <p>
        {{ returnValue }}
        {{ JSON.stringify(returnValue) }}
    </p>

    <br />
    <br />

    <p>Returned name: {{ salesChannel.name }}</p>
    <p>Returned active state: {{ salesChannel.active }}</p>

    <br />
    <br />

    <sw-text-field label="name" v-model="salesChannel.name"></sw-text-field>
    <sw-button @click="updateDataset">Update to main</sw-button>
</div>
`,
    components: {
        'sw-button': SwButton,
        'sw-text-field': SwTextField,
    },
    data(): {
        salesChannel: {
            name?: string,
            active?: boolean,
        },
        clicked: string,
        returnValue: unknown,
        retryCounter: number,
    } {
        return {
            salesChannel: {},
            clicked: '',
            returnValue: undefined,
            retryCounter: 0,
        }
    },
    methods: {
        getDataset() {
            if (this.retryCounter >= 10) {
                this.returnValue = 'Retry limit reached';

                return;
            }

            this.clicked = 'getDataset';
            this.retryCounter++;

            data.get({
                id: 'sw-sales-channel-detail__salesChannel',
                selectors: ['name', 'active'],
            }).then((data) => {
                this.retryCounter = 0;
                this.returnValue = data;
                // @ts-expect-error
                const { name, active } = data;

                this.salesChannel = { name, active };
            }).catch((error) => {
                this.returnValue = error;

                window.setTimeout(() => {
                    this.getDataset();
                }, 500);
            });
        },
        async updateDataset() {
            this.clicked = 'updateDataset';

            await data.update({
                id: 'sw-sales-channel-detail__salesChannel',
                data: this.salesChannel,
            })
        },
        subscribeData() {
            this.clicked = 'subscribeData';

            data.subscribe('sw-sales-channel-detail__salesChannel', (data) => {
                this.returnValue = data;

                this.salesChannel = {
                    // @ts-expect-error
                    name: data.data.name,
                }
            }, {
                selectors: ['name'],
            });
        }
    }
})
