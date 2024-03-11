import Vue from 'vue'
import { location } from "@shopware-ag/meteor-admin-sdk";
import { SwButton, SwNumberField } from '@shopware-ag/meteor-component-library';

export default Vue.extend({
    components: {
        'sw-button': SwButton,
        'sw-number-field': SwNumberField,
    },
    template: `
        <div :style="componentStyling">
        <p>Auto-Resize: {{ isAutoResizing ? 'On' : 'Off' }}</p>
        <sw-number-field :value="heightInput" @input-change="setHeightInput"></sw-number-field>

        <br><br>

        <sw-button @click="changeHeight">Update height using auto resizing</sw-button>
        <br>
        <sw-button @click="changeHeightManually">Update height manually</sw-button>

        <br><br>

        <sw-button @click="startAutoResizing">Start auto resizing</sw-button>
        <sw-button @click="stopAutoResizing">Stop auto resizing</sw-button>

        </div>
    `,
    data() {
        return {
            heightInput: 123,
            height: 400,
            isAutoResizing: true,
        }
    },
    computed: {
        componentStyling(): {} {
            return {
                height: `${this.height}px`,
                backgroundColor: '#0e82ff',
                padding: '10px',
            }
        }
    },
    methods: {
        setHeightInput(height: number): void {
            this.heightInput = height;
        },

        changeHeight():void {
            this.height = Math.floor(this.heightInput);
        },

        changeHeightManually():void {
            void location.updateHeight(this.heightInput);
        },

        async stopAutoResizing(): Promise<void> {
            await location.stopAutoResizer();
            this.isAutoResizing = false;
        },

        async startAutoResizing(): Promise<void> {
            await location.startAutoResizer();
            this.isAutoResizing = false;
        }
    }
})

