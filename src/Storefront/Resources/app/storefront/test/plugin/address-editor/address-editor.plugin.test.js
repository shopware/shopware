import AddressEditorPlugin from 'src/plugin/address-editor/address-editor.plugin';
import FormAjaxSubmitPlugin from 'src/plugin/forms/form-ajax-submit.plugin';

/**
 * @package checkout
 */
describe('AddressEditorPlugin test', () => {
    let addressEditor;
    let formAjaxSubmit;

    beforeEach(() => {
        document.body.innerHTML = `
            <button class="btn" data-address-editor="true">Open address editor</button>

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
                    <div id="shipping-address-create-edit" class="collapse" data-parent="#shipping-addressEditorAccordion">
                    </div>
                </div>
            </div>
        `;

        const element = document.querySelector('.btn');

        window.focusHandler = {
            saveFocusState: jest.fn(),
            resumeFocusState: jest.fn(),
        };

        addressEditor = new AddressEditorPlugin(element, {
            url: '/widgets/account/address-book',
            changeShipping: true,
        });

        // Return address editor template when calling HttpClient post
        addressEditor._client.post = jest.fn((url, data, callback) => {
            callback(addressEditorTemplate);
        });

        document.body.insertAdjacentElement = jest.fn();
        window.PluginManager.initializePlugins = jest.fn(() => Promise.resolve());

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

    test('should not close modal if there is an invalid field', async () => {
        const addressEditorTemplate = `
            <div class="js-address-editor">
                <button class="edit-address" data-toggle="collapse" data-target="#billing-address-create-edit">Edit address</button>

                <div id="billing-addressEditorAccordion">
                    <div id="billing-address-create-edit" class="collapse" data-parent="#billing-addressEditorAccordion">
                        <form method="post" data-form-ajax-submit="true" class="js-close-address-editor">
                            <input type="text" class="form-control is-invalid" id="billing-addresscompany" placeholder="Enter company..." name="address[company]" value="">
                            <div class="address-form-actions">
                                <button class="address-form-submit btn btn-primary" title="Save address">
                                    Save address
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `;

        const element = document.querySelector('.btn');

        const spyOnWindowLocationAssign = jest.spyOn(window.location, 'assign');

        // Return address editor template when calling HttpClient post
        addressEditor._client.post = jest.fn((url, data, callback) => {
            callback(addressEditorTemplate);
        });

        // Click open button
        element.dispatchEvent(new Event('click', { bubbles: true }));

        window.PluginManager.getPluginInstanceFromElement = (element, pluginName) => {
            if (pluginName === 'FormAjaxSubmit') {
                formAjaxSubmit = new FormAjaxSubmitPlugin(element, {
                    replaceSelectors: [
                        '#billing-address-create-edit',
                    ],
                });
                return formAjaxSubmit;
            }

            return {};
        };

        jest.runAllTimers();
        await new Promise(process.nextTick);

        expect(typeof formAjaxSubmit).toBe('object');
        expect(formAjaxSubmit instanceof FormAjaxSubmitPlugin).toBe(true);

        formAjaxSubmit._callbacks[0]();

        // Should not reload window
        expect(spyOnWindowLocationAssign).not.toHaveBeenCalled();
    });

    test('should close modal if there is not invalid field', async () => {
        const addressEditorTemplate = `
            <div class="js-address-editor">
                <button class="edit-address" data-toggle="collapse" data-target="#billing-address-create-edit">Edit address</button>

                <div id="billing-addressEditorAccordion">
                    <div id="billing-address-create-edit" class="collapse" data-parent="#billing-addressEditorAccordion">
                        <form method="post" data-form-ajax-submit="true" class="js-close-address-editor">
                            <input type="text" class="form-control" id="billing-addresscompany" placeholder="Enter company..." name="address[company]" value="">
                            <div class="address-form-actions">
                                <button class="address-form-submit btn btn-primary" title="Save address">
                                    Save address
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `;

        const element = document.querySelector('.btn');

        const spyOnWindowLocationAssign = jest.spyOn(window.location, 'assign');

        // Return address editor template when calling HttpClient post
        addressEditor._client.post = jest.fn((url, data, callback) => {
            callback(addressEditorTemplate);
        });

        // Click open button
        element.dispatchEvent(new Event('click', { bubbles: true }));

        window.PluginManager.getPluginInstanceFromElement = (element, pluginName) => {
            if (pluginName === 'FormAjaxSubmit') {
                formAjaxSubmit = new FormAjaxSubmitPlugin(element, {
                    replaceSelectors: [
                        '#billing-address-create-edit',
                    ],
                });
                return formAjaxSubmit;
            }

            return {};
        };

        jest.runAllTimers();
        await new Promise(process.nextTick);

        expect(typeof formAjaxSubmit).toBe('object');
        expect(formAjaxSubmit instanceof FormAjaxSubmitPlugin).toBe(true);

        formAjaxSubmit._callbacks[0]();

        // Should not reload window
        expect(spyOnWindowLocationAssign).toHaveBeenCalled();
    });
});
