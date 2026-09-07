import CompanyNameFieldsPlugin from 'src/plugin/forms/company-name-fields.plugin';
import FormValidation from 'src/helper/form-validation.helper';

describe('CompanyNameFieldsPlugin', () => {
    let wrapper;
    let select;

    const buildDom = (selectedAccountType = 'private') => {
        document.body.innerHTML = `
            <form action="/register" method="post">
                <select id="accountType" name="accountType">
                    <option value="private">Private</option>
                    <option value="business">Business</option>
                </select>

                <div class="row g-2" data-company-name-fields="true">
                    <label for="firstName">First name<span class="form-required-label" aria-hidden="true"> *</span></label>
                    <input id="firstName" name="firstName" data-validation="required" aria-required="true">

                    <label for="lastName">Last name<span class="form-required-label" aria-hidden="true"> *</span></label>
                    <input id="lastName" name="lastName" data-validation="required" aria-required="true">
                </div>
            </form>
        `;

        wrapper = document.querySelector('[data-company-name-fields]');
        select = document.querySelector('#accountType');
        select.value = selectedAccountType;
    };

    const createPlugin = ({ shown = true, required = true } = {}) => new CompanyNameFieldsPlugin(wrapper, {
        accountTypeSelector: '#accountType',
        businessValue: 'business',
        shown,
        required,
    });

    const selectAccountType = (value) => {
        select.value = value;
        select.dispatchEvent(new Event('change'));
    };

    const firstName = () => document.querySelector('#firstName');

    beforeEach(() => {
        window.formValidation = new FormValidation();
    });

    afterEach(() => {
        document.body.innerHTML = '';
    });

    it('keeps the names required for a private account', () => {
        buildDom();
        createPlugin({ shown: true, required: false });

        expect(firstName().getAttribute('data-validation')).toBe('required');
        expect(firstName().getAttribute('aria-required')).toBe('true');
        expect(wrapper.classList.contains('d-none')).toBe(false);
    });

    it('drops the required rule when a company account is selected', () => {
        buildDom();
        createPlugin({ shown: true, required: false });

        selectAccountType('business');

        expect(firstName().getAttribute('data-validation')).toBe('');
        expect(firstName().hasAttribute('aria-required')).toBe(false);
        expect(document.querySelectorAll('.form-required-label')).toHaveLength(0);
        expect(firstName().hasAttribute('disabled')).toBe(false);
        expect(wrapper.classList.contains('d-none')).toBe(false);
    });

    it('keeps the required rule when a company account still needs the names', () => {
        buildDom();
        createPlugin({ shown: true, required: true });

        selectAccountType('business');

        expect(firstName().getAttribute('data-validation')).toBe('required');
        expect(firstName().getAttribute('aria-required')).toBe('true');
        expect(wrapper.classList.contains('d-none')).toBe(false);
    });

    it('hides and disables the names when a company account does not show them', () => {
        buildDom();
        createPlugin({ shown: false, required: true });

        selectAccountType('business');

        expect(wrapper.classList.contains('d-none')).toBe(true);
        expect(firstName().hasAttribute('disabled')).toBe(true);
        expect(firstName().hasAttribute('aria-required')).toBe(false);
    });

    it('restores the names when switching back to a private account', () => {
        buildDom();
        createPlugin({ shown: false, required: true });

        selectAccountType('business');
        selectAccountType('private');

        expect(wrapper.classList.contains('d-none')).toBe(false);
        expect(firstName().hasAttribute('disabled')).toBe(false);
        expect(firstName().getAttribute('data-validation')).toBe('required');
    });

    it('applies the company state when the select is already set to business on load', () => {
        buildDom('business');
        createPlugin({ shown: true, required: false });

        expect(firstName().getAttribute('data-validation')).toBe('');
        expect(firstName().hasAttribute('aria-required')).toBe(false);
    });

    it('leaves the fields alone when the account type select is not rendered', () => {
        buildDom();
        select.remove();

        createPlugin({ shown: true, required: false });

        expect(firstName().getAttribute('data-validation')).toBe('required');
        expect(firstName().getAttribute('aria-required')).toBe('true');
    });
});
