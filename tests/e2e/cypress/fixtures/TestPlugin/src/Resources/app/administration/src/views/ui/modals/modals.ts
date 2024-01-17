import Vue from 'vue'
import { ui } from '@shopware-ag/meteor-admin-sdk';

// make background color white
document.body.style.backgroundColor = 'white';

export default Vue.extend({
    template: `
<div>
    <h1>Hello in the example card</h1>
    <button @click="openModal">Open Modal</button>
    <button @click="openModalnoHeader">Open No Header</button>
    <button @click="openModalsmallVariant">Open small variant</button>
    <button @click="openModalnoneClosable">Open none closable</button>
</div>
`,
    methods: {
        openModal() {
            ui.modal.open({
                title: 'Hello from the plugin',
                locationId: 'ui-modals-modal-content',
                buttons: [
                    {
                        label: 'Close modal',
                        variant: 'primary',
                        method: () => {
                            ui.modal.close({
                                locationId: 'ui-modals-modal-content'
                            })
                        }
                    }
                ]
            })
        },
        openModalnoHeader() {
            ui.modal.open({
                title: 'Hello from the plugin',
                showHeader: false,
                locationId: 'ui-modals-modal-content',
                buttons: [
                    {
                        label: 'Close modal',
                        variant: 'primary',
                        method: () => {
                            ui.modal.close({
                                locationId: 'ui-modals-modal-content'
                            })
                        }
                    }
                ]
            })
        },
        openModalsmallVariant() {
            ui.modal.open({
                title: 'Hello from the plugin',
                variant: "small",
                locationId: 'ui-modals-modal-content',
                buttons: [
                    {
                        label: 'Close modal',
                        variant: 'primary',
                        method: () => {
                            ui.modal.close({
                                locationId: 'ui-modals-modal-content'
                            })
                        }
                    }
                ]
            })
        },
        openModalnoneClosable() {
            ui.modal.open({
                title: 'Hello from the plugin',
                closable: false,
                locationId: 'ui-modals-modal-content',
                buttons: [
                    {
                        label: 'Close modal',
                        variant: 'primary',
                        method: () => {
                            ui.modal.close({
                                locationId: 'ui-modals-modal-content'
                            })
                        }
                    }
                ]
            });
        },
    }
})
