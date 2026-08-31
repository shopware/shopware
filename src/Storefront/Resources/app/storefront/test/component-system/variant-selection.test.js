import 'src/component-system/component';
import VariantSelection from '../../../../views/components/Sw/Product/VariantSelection';

describe('views/components/Sw/Product/VariantSelection', () => {
    let form;
    let component;
    let fetch;

    beforeEach(() => {
        form = document.createElement('form');
        form.innerHTML = `
            <input type="radio" name="color" value="red" id="red" checked>
            <input type="radio" name="color" value="blue" id="blue">
            <input type="radio" name="size" value="large" disabled>
            <select name="material">
                <option value="cotton" selected>Cotton</option>
                <option value="wool">Wool</option>
            </select>
        `;
        document.body.appendChild(form);

        window.focusHandler = {
            resumeFocusStatePersistent: jest.fn(),
            saveFocusStatePersistent: jest.fn(),
        };
        fetch = jest.fn().mockResolvedValue({
            json: jest.fn().mockResolvedValue({ url: '/detail/variant' }),
        });
        window.fetch = fetch;

        component = new VariantSelection(form, {
            url: '/detail/switch/parent',
            focusHandlerKey: 'product-variant-selection',
        });
    });

    afterEach(() => {
        component.destroy();
        document.body.innerHTML = '';
        window.focusHandler = undefined;
        window.fetch = undefined;
    });

    test('serializes checked and selected controls while ignoring disabled controls', () => {
        expect(component.serialize()).toEqual({
            color: 'red',
            material: 'cotton',
        });
        expect(window.focusHandler.resumeFocusStatePersistent).toHaveBeenCalledWith('product-variant-selection');
    });

    test('switches the variant with the current selections and preserves focus', async () => {
        const switchedInput = form.querySelector('input[value="blue"]');
        const redirectToVariant = jest.spyOn(component, 'redirectToVariant').mockImplementation(() => {});

        switchedInput.checked = true;
        switchedInput.dispatchEvent(new Event('change', { bubbles: true }));
        await new Promise((resolve) => setTimeout(resolve, 0));

        expect(window.focusHandler.saveFocusStatePersistent).toHaveBeenCalledWith(
            'product-variant-selection',
            '[id="blue"]',
        );
        expect(fetch).toHaveBeenCalledWith(
            '/detail/switch/parent?switched=color&options=%7B%22color%22%3A%22blue%22%2C%22material%22%3A%22cotton%22%7D',
            { headers: { 'X-Requested-With': 'XMLHttpRequest' } },
        );
        expect(redirectToVariant).toHaveBeenCalledWith('/detail/variant');
    });

    test('removes the change listener when destroyed', () => {
        component.destroy();

        form.querySelector('input[value="blue"]').dispatchEvent(new Event('change', { bubbles: true }));

        expect(fetch).not.toHaveBeenCalled();
    });
});
