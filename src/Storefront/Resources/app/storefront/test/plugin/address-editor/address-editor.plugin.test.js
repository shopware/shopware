import AddressEditorPlugin from 'src/plugin/address-editor/address-editor.plugin';
import '../../../node_modules/bootstrap';

jest.mock('src/plugin-system/plugin.manager', () => ({
    default: {
        getPluginInstances: () => {
            return [];
        },
    },
}));

/**
 * @package checkout
 */
describe('AddressEditorPlugin test', () => {
    let addressEditor;

    beforeEach(() => {
        document.body.innerHTML = `
            <button class="btn">Open address editor</button>

            <div class="js-pseudo-modal-template">
                <div class="modal fade" tabindex="-1" role="dialog">
                    <div class="modal-dialog" role="document">
                         <div class="modal-content">
                             <div class="modal-header only-close">
                                 <h5 class="modal-title js-pseudo-modal-template-title-element"></h5>
                                 <button type="button" class="modal-close close" data-dismiss="modal" aria-label="Close">x</button>
                             </div>
                             <div class="modal-body js-pseudo-modal-template-content-element">
                             </div>
                         </div>
                     </div>
                 </div>
             </div>
         `;

        const addressEditorTemplate = `
            <div class="js-address-editor">
                <button class="edit-address" data-toggle="collapse" data-target="#shipping-address-create-edit">Edit address</button>

                <div id="shipping-addressEditorAccordion">
                    <div id="shipping-address-create-edit" class="collapse" data-parent="#shipping-addressEditorAccordion"></div>
                </div>
            </div>
        `;

        const element = document.querySelector('.btn');

        addressEditor = new AddressEditorPlugin(element, {
            url: '/widgets/account/address-book',
            changeShipping: true,
        });

        // Return address editor template when calling HttpClient post
        addressEditor._client.post = jest.fn((url, data, callback) => {
            callback(addressEditorTemplate);
        });

        document.body.insertAdjacentElement = jest.fn();
        window.PluginManager.initializePlugins = jest.fn();

        jest.useFakeTimers();
    });

    afterEach(() => {
        document.body.innerHTML = '';
        jest.useRealTimers();
    });

    test('plugin initializes', () => {
        expect(typeof addressEditor).toBe('object');
        expect(addressEditor).toBeInstanceOf(AddressEditorPlugin);
    });

    test('open address editor modal', () => {
        const button = document.querySelector('.btn');

        // Click open button
        button.dispatchEvent(new Event('click', { bubbles: true }));

        jest.runAllTimers();

        const expectedRequestData = {
            id: false,
            changeableAddresses: {
                changeShipping: true,
                changeBilling: false,
            },
        };

        expect(window.PluginManager.initializePlugins).toHaveBeenCalledTimes(1);
        expect(addressEditor._client.post).toHaveBeenCalledWith(
            '/widgets/account/address-book',
            JSON.stringify(expectedRequestData),
            expect.any(Function)
        );
    });
});
