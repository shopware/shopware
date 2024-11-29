import FormHandler from 'src/plugin/forms/form-handler.plugin';
import FormValidation from 'src/helper/form-validation.helper';

describe('FormHandler Plugin', () => {
    let form;
    let formHandlerPlugin;

    beforeEach(async () => {
        document.body.innerHTML = `
            <form id="testForm">
                <div class="form-group">
                    <label for="name">Username</label>
                    <input type="text" name="name" id="name" data-validation="required" aria-describedby="name-feedback">
                    <div id="name-feedback" class="form-field-feedback"></div>
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" data-validation="required,email" aria-describedby="email-feedback">
                    <div id="email-feedback" class="form-field-feedback"></div>
                </div>

                <div class="form-group">
                    <label for="emailConfirmation">Email Confirmation</label>
                    <input type="email" id="emailConfirmation" data-validation="required,confirmation" aria-describedby="emailConfirmation-feedback">
                    <div id="emailConfirmation-feedback" class="form-field-feedback"></div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" minlength="12" data-validation="required,minLength" aria-describedby="password-feedback">
                    <div id="password-feedback" class="form-field-feedback"></div>
                </div>

                <div class="form-group">
                    <input type="checkbox" id="privacy" data-validation="required" aria-describedby="privacy-feedback">
                    <label for="privacy">Privacy</label>
                    <div id="privacy-feedback" class="form-field-feedback"></div>
                </div>

                <button type="submit">Submit</button>
            </form>

            <button type="submit" form="testForm" class="outer-submit-button">Submit</button>
        `;

        window.validationMessages = {
            required: 'Input should not be empty.',
            email: 'Invalid email address.',
            confirmation: 'Confirmation field does not match.',
            minLength: 'Input is too short.',
        };

        window.formValidation = new FormValidation();

        form = document.getElementById('testForm');

        formHandlerPlugin = new FormHandler(form);
    });

    test('should init form handling', () => {
        expect(formHandlerPlugin.formFields.length).toBe(5);
        expect(formHandlerPlugin.submitButtons.length).toBe(2);
        expect(form.getAttribute('novalidate')).toBeDefined();
    });

    test('should validate field on value change', () => {
        jest.useFakeTimers();

        const emailField = document.getElementById('email');
        const emailFeedback = document.getElementById('email-feedback');

        // Mocking `checkVisibility` method, because Jest does not support it.
        emailField.checkVisibility = jest.fn().mockReturnValue(true);

        emailField.value = 'test';
        emailField.dispatchEvent(new Event('input'));

        // The input event handler is debounced.
        jest.runAllTimers();

        expect(emailField.classList).toContain(window.formValidation.config.invalidClass);
        expect(emailFeedback.innerHTML).toBe('<div class="invalid-feedback">Invalid email address.</div>');

        emailField.value = 'test@test.com';
        emailField.dispatchEvent(new Event('input'));

        jest.runAllTimers();

        expect(emailField.classList).not.toContain(window.formValidation.config.invalidClass);
        expect(emailFeedback.innerHTML).toBe('');
    });

    test('should validate form on submit', () => {
        const submitEvent = new Event('submit', { cancelable: true });
        const eventSpy = jest.spyOn(submitEvent, 'preventDefault');

        const nameField = document.getElementById('name');
        const formFields = form.querySelectorAll('input');

        // Mocking `checkVisibility` method, because Jest does not support it.
        formFields.forEach((field) => {
            field.checkVisibility = jest.fn().mockReturnValue(true);
        });

        form.dispatchEvent(submitEvent);

        // The submit event should be prevented, because the fields are empty.
        expect(eventSpy).toHaveBeenCalledTimes(1);

        // Fields should be marked as invalid.
        expect(nameField.classList).toContain(window.formValidation.config.invalidClass);

        // The first invalid field should get focus.
        expect(document.activeElement).toBe(nameField);
    });

    test('should do custom validity check', () => {
        const formValidationSpy = jest.spyOn(window.formValidation, 'validateForm');
        const formFields = form.querySelectorAll('input');

        // Mocking `checkVisibility` method, because Jest does not support it.
        formFields.forEach((field) => {
            field.checkVisibility = jest.fn().mockReturnValue(true);
        });

        const validationResult = form.checkValidity();

        expect(formValidationSpy).toHaveBeenCalledTimes(1);
        expect(validationResult).toBe(false);
    });
});
