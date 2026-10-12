import GaranLabelTogglePlugin from 'src/plugin/garan-label/garan-label-toggle.plugin';

/**
 * @sw-package inventory
 */
describe('GaranLabelTogglePlugin tests', () => {
    let wrapper;

    beforeEach(() => {
        document.body.innerHTML = `
            <div class="product-detail-garan-label-wrapper" data-garan-label-toggle="true">
                <div class="product-detail-garan-label">
                    <button type="button" class="garan-label-button">nested</button>
                </div>
                <div class="product-detail-garan-label-full d-none">full</div>
                <button type="button"
                        class="product-detail-garan-label-show-link"
                        data-garan-label-toggle-trigger="true"
                        data-garan-label-show-text="Show GARAN label"
                        data-garan-label-hide-text="Hide GARAN label">
                    <span class="product-detail-garan-label-show-link-text">Show GARAN label</span>
                    <span class="product-detail-garan-label-show-link-icon-show"></span>
                    <span class="product-detail-garan-label-show-link-icon-hide d-none"></span>
                </button>
            </div>
        `;

        wrapper = document.querySelector('[data-garan-label-toggle]');
        new GaranLabelTogglePlugin(wrapper);
    });

    const preview = () => wrapper.querySelector('.product-detail-garan-label');
    const full = () => wrapper.querySelector('.product-detail-garan-label-full');
    const trigger = () => wrapper.querySelector('[data-garan-label-toggle-trigger]');
    const text = () => wrapper.querySelector('.product-detail-garan-label-show-link-text');
    const iconShow = () => wrapper.querySelector('.product-detail-garan-label-show-link-icon-show');
    const iconHide = () => wrapper.querySelector('.product-detail-garan-label-show-link-icon-hide');

    const expectExpanded = (expanded) => {
        expect(preview().classList.contains('d-none')).toBe(expanded);
        expect(full().classList.contains('d-none')).toBe(!expanded);
        expect(text().textContent).toBe(expanded ? 'Hide GARAN label' : 'Show GARAN label');
        expect(iconShow().classList.contains('d-none')).toBe(expanded);
        expect(iconHide().classList.contains('d-none')).toBe(!expanded);
    };

    test('the trigger expands and collapses the full label', () => {
        expectExpanded(false);

        trigger().click();
        expectExpanded(true);

        trigger().click();
        expectExpanded(false);
    });

    test('clicking the nested label expands the full label and focuses the trigger', () => {
        wrapper.querySelector('.garan-label-button').click();

        expectExpanded(true);
        expect(document.activeElement).toBe(trigger());
    });

    test('clicking next to the nested label does not expand the full label', () => {
        preview().click();

        expectExpanded(false);
    });
});
