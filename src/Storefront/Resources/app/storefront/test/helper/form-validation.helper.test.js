import FormValidation from 'src/helper/form-validation.helper';

describe('form-validation', () => {
    let formValidation;
    let formTemplate;

    beforeEach(async () => {
        window.validationMessages = {
            required: 'Input should not be empty.',
            email: 'Invalid email address.',
            confirmation: 'Confirmation field does not match.',
            minLength: 'Input is too short.',
        };

        formValidation = new FormValidation();

        formTemplate = `
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

                <button type="submit">Submit</button>
            </form>
        `;
    });

    test('should initialize default validators', () => {
        expect(formValidation.validators.get('required')).toBeDefined();
        expect(formValidation.validators.get('email')).toBeDefined();
        expect(formValidation.validators.get('confirmation')).toBeDefined();
        expect(formValidation.validators.get('minLength')).toBeDefined();
    });

    test('should add validator', () => {
        const validatorAddition = formValidation.addValidator('custom', () => {
            return false;
        }, 'Custom validation failed.');

        expect(validatorAddition).toBe(true);
        expect(formValidation.validators.get('custom')).toBeDefined();
    });

    test('should add error message', () => {
        const messageAddition = formValidation.addErrorMessage('custom', 'Custom validation failed');

        expect(messageAddition).toBe(true);
        expect(formValidation.errorMessages.get('custom')).toBeDefined();
    });

    test('should set config', () => {
        expect(formValidation.config.validClass).toBe('is-valid');

        formValidation.setConfig('validClass', 'valid');

        expect(formValidation.config.validClass).toBe('valid');
    });

    test('should validate form', () => {
        document.body.innerHTML = formTemplate;

        const form = document.getElementById('testForm');
        const formFields = form.querySelectorAll('input');

        // Mocking `checkVisibility` method, because Jest does not support it.
        formFields.forEach((field) => {
            field.checkVisibility = jest.fn().mockReturnValue(true);
        });

        // No fields filled out
        let invalidFields = formValidation.validateForm(form);
        expect(invalidFields.length).toBe(4);

        // Valid name field
        const nameField = document.getElementById('name');
        nameField.value = 'Jon Doe';

        invalidFields = formValidation.validateForm(form);
        expect(invalidFields.length).toBe(3);

        // Invalid email field
        const emailField = document.getElementById('email');
        const emailFeedback = document.getElementById('email-feedback');
        emailField.value = 'test';

        invalidFields = formValidation.validateForm(form);
        expect(invalidFields.length).toBe(3);
        expect(emailField.classList).toContain(formValidation.config.invalidClass);
        expect(emailFeedback.innerHTML).toBe('<div class="invalid-feedback">Invalid email address.</div>');

        // Valid email field
        emailField.value = 'test@test.com';

        invalidFields = formValidation.validateForm(form);
        expect(invalidFields.length).toBe(2);
        expect(emailField.classList).not.toContain(formValidation.config.invalidClass);
        expect(emailFeedback.innerHTML).toBe('');

        // Invalid confirmation field
        const emailConfirmationField = document.getElementById('emailConfirmation');
        const emailConfirmationFeedback = document.getElementById('emailConfirmation-feedback');
        emailConfirmationField.value = 'test';

        invalidFields = formValidation.validateForm(form);
        expect(invalidFields.length).toBe(2);
        expect(emailConfirmationField.classList).toContain(formValidation.config.invalidClass);
        expect(emailConfirmationFeedback.innerHTML).toBe('<div class="invalid-feedback">Confirmation field does not match.</div>');

        // Valid confirmation field
        emailConfirmationField.value = 'test@test.com';

        invalidFields = formValidation.validateForm(form);
        expect(invalidFields.length).toBe(1);
        expect(emailConfirmationField.classList).not.toContain(formValidation.config.invalidClass);
        expect(emailConfirmationFeedback.innerHTML).toBe('');

        // Invalid password field
        const passwordField = document.getElementById('password');
        const passwordFeedback = document.getElementById('password-feedback');
        passwordField.value = 'asdf1234';

        invalidFields = formValidation.validateForm(form);
        expect(invalidFields.length).toBe(1);
        expect(passwordField.classList).toContain(formValidation.config.invalidClass);
        expect(passwordFeedback.innerHTML).toBe('<div class="invalid-feedback">Input is too short.</div>');

        // Valid password field
        passwordField.value = 'asdf12346789#';

        invalidFields = formValidation.validateForm(form);
        expect(invalidFields.length).toBe(0);
        expect(passwordField.classList).not.toContain(formValidation.config.invalidClass);
        expect(passwordFeedback.innerHTML).toBe('');
    });

    test('should validate required checkbox fields', () => {
        document.body.innerHTML = `
            <form id="testForm">
                <div class="form-group">
                    <label for="name">Username</label>
                    <input type="text" name="name" id="name" data-validation="required" aria-describedby="name-feedback">
                    <div id="name-feedback" class="form-field-feedback"></div>
                </div>

                <div class="form-group">
                    <input type="checkbox" id="privacy" data-validation="required" aria-describedby="privacy-feedback">
                    <label for="privacy">Privacy</label>
                    <div id="privacy-feedback" class="form-field-feedback"></div>
                </div>
            </form>
        `;

        const form = document.getElementById('testForm');
        const formFields = form.querySelectorAll('input');

        // Mocking `checkVisibility` method, because Jest does not support it.
        formFields.forEach((field) => {
            field.checkVisibility = jest.fn().mockReturnValue(true);
        });

        // Text field
        const textField = document.getElementById('name');

        // Invalid required text field
        let validationErrors = formValidation.validateField(textField);
        expect(validationErrors.length).toBe(1);

        // Valid required text field
        textField.value = 'Jon Doe';

        validationErrors = formValidation.validateField(textField);
        expect(validationErrors.length).toBe(0);

        // Checkbox field
        const checkboxField = document.getElementById('privacy');

        validationErrors = formValidation.validateField(checkboxField);
        expect(validationErrors.length).toBe(1);

        // Valid checkbox field
        checkboxField.setAttribute('checked', 'checked');

        validationErrors = formValidation.validateField(checkboxField);
        expect(validationErrors.length).toBe(0);
    });

    test('should set field as required', () => {
        document.body.innerHTML = `
            <form id="testForm">
                <div class="form-group">
                    <label for="name">Username</label>
                    <input type="text" name="name" id="name" aria-describedby="name-feedback">
                    <div id="name-feedback" class="form-field-feedback"></div>
                </div>
            </form>
        `;

        const form = document.getElementById('testForm');
        const field = document.getElementById('name');

        // Mocking `checkVisibility` method, because Jest does not support it.
        field.checkVisibility = jest.fn().mockReturnValue(true);

        // Validation should succeed, because no validation rules applied.
        let invalidFields = formValidation.validateForm(form);
        expect(invalidFields.length).toBe(0);

        formValidation.setFieldRequired(field);

        invalidFields = formValidation.validateForm(form);
        expect(invalidFields.length).toBe(1);
        expect(field.classList).toContain(formValidation.config.invalidClass);
    });

    test('should add novalidate attribute', () => {
        document.body.innerHTML = formTemplate;

        const form = document.getElementById('testForm');

        expect(form.getAttribute('novalidate')).toBeNull();

        formValidation.setNoValidate(form);

        expect(form.getAttribute('novalidate')).toBeDefined();
    });

    test('should check for form element type', () => {
        document.body.innerHTML = formTemplate;

        const form = document.getElementById('testForm');

        expect(formValidation.isFormElement(form)).toBe(true);
    });

    test('should use custom validator', () => {
        document.body.innerHTML = `
            <form id="testForm">
                <div class="form-group">
                    <label for="name">Username</label>
                    <input type="text" name="name" id="name" data-validation="required,custom" aria-describedby="name-feedback">
                    <div id="name-feedback" class="form-field-feedback"></div>
                </div>
            </form>
        `;

        const field = document.getElementById('name');

        // Mocking `checkVisibility` method, because Jest does not support it.
        field.checkVisibility = jest.fn().mockReturnValue(true);

        const validator = jest.fn(value => value === 'test');

        formValidation.addValidator('custom', validator, 'Custom validation failed.');

        // Custom validator should be invalid.
        formValidation.validateField(field);

        expect(validator).toHaveBeenCalledTimes(1);
        expect(validator).toHaveReturnedWith(false);
        expect(field.classList).toContain(formValidation.config.invalidClass);

        // Custom validator should be valid.
        field.value = 'test';
        formValidation.validateField(field);

        expect(validator).toHaveBeenCalledTimes(2);
        expect(validator).toHaveReturnedWith(true);
        expect(field.classList).not.toContain(formValidation.config.invalidClass);
    });

    test('should validate hidden fields correctly', () => {
        document.body.innerHTML = `
            <form id="testForm">
                <!-- Should be validated-->
                <input type="text"
                       name="visible"
                       id="visible"
                       data-validation="required">

                <!-- Should not be validated-->
                <input type="text"
                       name="invisible"
                       id="invisible"
                       data-validation="required"
                       style="display: none;">

                <!-- Should be validated-->
                <input type="text"
                       name="invisible-but-validated"
                       id="invisible-but-validated"
                       data-validation="required"
                       data-validate-hidden="true"
                       style="display: none;">

                <!-- Should be validated-->
                <input type="hidden"
                       name="hidden"
                       id="hidden"
                       data-validation="required">
            </form>
        `;

        const form = document.getElementById('testForm');
        const visibleField = document.getElementById('visible');
        const invisibleField = document.getElementById('invisible');
        const invisibleButValidatedField = document.getElementById('invisible-but-validated');
        const hiddenField = document.getElementById('hidden');

        // Mocking `checkVisibility` method, because Jest does not support it.
        visibleField.checkVisibility = jest.fn().mockReturnValue(true);
        invisibleField.checkVisibility = jest.fn().mockReturnValue(false);
        invisibleButValidatedField.checkVisibility = jest.fn().mockReturnValue(false);
        hiddenField.checkVisibility = jest.fn().mockReturnValue(false);

        let invalidFields = formValidation.validateForm(form);

        expect(invalidFields.length).toBe(3);

        visibleField.value = 'Test';
        invisibleField.value = 'Test';
        invisibleButValidatedField.value = 'Test';
        hiddenField.value = 'Test';

        invalidFields = formValidation.validateForm(form);

        expect(invalidFields.length).toBe(0);
    });

    test('should validate field with native required attribute', () => {
        document.body.innerHTML = `
            <form id="testForm">
                <input type="text" name="required" id="required" data-validation="required">
                <input type="text" name="required-native" id="required-native" required>
                <input type="text" name="not-required" id="not-required">
            </form>
        `;

        const form = document.getElementById('testForm');
        const formFields = form.querySelectorAll('input');

        // Mocking `checkVisibility` method, because Jest does not support it.
        formFields.forEach((field) => {
            field.checkVisibility = jest.fn().mockReturnValue(true);
        });

        const requiredField = document.getElementById('required');
        const nativeRequiredField = document.getElementById('required-native');
        const notRequiredField = document.getElementById('not-required');

        let invalidFields = formValidation.validateForm(form);

        expect(invalidFields.length).toBe(2);

        requiredField.value = 'Test';
        nativeRequiredField.value = 'Test';
        notRequiredField.value = 'Test';

        invalidFields = formValidation.validateForm(form);

        expect(invalidFields.length).toBe(0);
    });
});
