import FormAjaxPaginationPlugin from 'src/plugin/forms/form-ajax-pagination.plugin';

const template = `
    <form 
        class="product-detail-review-pagination-form"
        action="/some/url"
        method="post"
        data-form-ajax-submit="true"
        data-form-ajax-pagination="true">

        <input type="hidden" name="p" value="1">

        <nav aria-label="pagination" class="pagination-nav">
            <ul class="pagination">
                <li class="page-item page-first"><a href="#" class="page-link" data-page="1" data-focus-id="first">First</a></li>
                <li class="page-item page-prev"><a href="#" class="page-link" data-page="1" data-focus-id="prev">Prev</a></li>
                <li class="page-item"><a href="#" class="page-link" data-page="1" data-focus-id="1">1</a></li>
                <!-- active page -->
                <li class="page-item active"><a href="#" class="page-link" data-page="2" data-focus-id="2">2</a></li>
                <li class="page-item"><a href="#" class="page-link" data-page="3" data-focus-id="3">3</a></li>
                <li class="page-item"><a href="#" class="page-link" data-page="4" data-focus-id="4">4</a></li>
                <li class="page-item"><a href="#" class="page-link" data-page="5" data-focus-id="5">5</a></li>
                <li class="page-item page-next"><a href="#" class="page-link" data-page="3" data-focus-id="next">Next</a></li>
                <li class="page-item page-last"><a href="#" class="page-link" data-page="42" data-focus-id="last">Last</a></li>
            </ul>
        </nav>
    </form>
`;

describe('FormAjaxPaginationPlugin tests', () => {
    let formAjaxPaginationPlugin;

    beforeEach(async () => {
        document.body.innerHTML = template;
        const element = document.querySelector('[data-form-ajax-pagination]');

        window.PluginManager.getPluginInstanceFromElement = () => {
            return {
                $emitter: {
                    subscribe: jest.fn(),
                },
            };
        }

        window.focusHandler = {
            saveFocusState: jest.fn(),
            resumeFocusState: jest.fn(),
        };

        formAjaxPaginationPlugin = new FormAjaxPaginationPlugin(element);
    });

    test('plugin instance is created', () => {
        expect(formAjaxPaginationPlugin).toBeInstanceOf(FormAjaxPaginationPlugin);
    });

    test('should modify hidden input during pagination item click', () => {
        const pageItem = document.querySelector('[data-page="3"]');

        // Click on page 3
        pageItem.dispatchEvent(new Event('click', { bubbles: true }));

        // Verify hidden input has the correct value
        expect(document.querySelector('input[name="p"]').value).toBe('3');
    });
});
