import Vue from 'vue'
import { ui } from '@shopware-ag/meteor-admin-sdk';

export default Vue.extend({
    template: `
<div>
  <h1>Hello from the plugin 👋🏻</h1>
  <button @click="closeModal">Close modal</button>
</div>
`,

    methods: {
        closeModal() {
            ui.modal.close({
                locationId: 'ui-modals-modal-content'
            })
        }
    }
})
