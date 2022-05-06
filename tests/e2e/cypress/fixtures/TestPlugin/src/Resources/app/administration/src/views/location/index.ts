import Vue from 'vue'
import { location } from "@shopware-ag/admin-extension-sdk";

export default Vue.extend({
    template: `
        <div :style="componentStyling">
            <p>Auto-Resize: {{ isAutoResizing ? 'On' : 'Off' }}</p>
            <input type="number" v-model="heightInput">

            <br><br>

            <button @click="changeHeight">Update height using auto resizing</button>
            <br>
            <button @click="changeHeightManually">Update height manually</button>

            <br><br>

            <button @click="startAutoResizing">Start auto resizing</button>
            <button @click="stopAutoResizing">Stop auto resizing</button>

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
                backgroundColor: '#0e82ff'
            }
        }
    },
    methods: {
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

