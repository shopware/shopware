<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Framework\Twig;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Twig\Environment;

/**
 * In production `formViolations` reaches the components as a Twig global
 * (`TemplateDataExtension::getGlobals()`). The tag-syntax tests below put it in the render context
 * instead, which lands in the same place, so the violation path is covered end to end.
 *
 * @internal
 */
#[Package('framework')]
class FormComponentsTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testInputRendersLabelAndValidationHooks(): void
    {
        $html = $this->render('Sw:Form:Input', [
            'name' => 'firstName',
            'label' => 'First name',
            'validationRules' => 'required',
        ]);

        static::assertStringContainsString('class="sw-form-input sw-form-field form-group"', $html);
        static::assertStringContainsString('class="sw-form-label form-label" for="firstName"', $html);
        static::assertStringContainsString('First name', $html);
        static::assertStringContainsString('class="sw-form-input__control sw-form-field__control form-control"', $html);
        static::assertStringContainsString('id="firstName"', $html);
        static::assertStringContainsString('name="firstName"', $html);
        static::assertStringContainsString('type="text"', $html);

        // The client validation reads the rules from the field and writes its messages into the
        // element referenced by aria-describedby, which it locates by the "feedback" in its id.
        static::assertStringContainsString('data-validation="required"', $html);
        static::assertStringContainsString('aria-required="true"', $html);
        static::assertStringContainsString('aria-describedby="firstName-feedback"', $html);
        static::assertStringContainsString('class="sw-form-feedback form-field-feedback" id="firstName-feedback"', $html);
    }

    /**
     * `setFieldRequired()` finds the marker through the label's `for`, and appends its own when
     * there is none.
     */
    public function testInputRendersTheRequiredMarkerOnlyForRequiredFields(): void
    {
        $required = $this->render('Sw:Form:Input', [
            'name' => 'firstName',
            'label' => 'First name',
            'validationRules' => 'required',
        ]);

        $optional = $this->render('Sw:Form:Input', [
            'name' => 'firstName',
            'label' => 'First name',
            'validationRules' => 'email',
        ]);

        static::assertStringContainsString('class="sw-form-label__required form-required-label" aria-hidden="true"', $required);
        static::assertStringNotContainsString('form-required-label', $optional);
        static::assertStringNotContainsString('aria-required', $optional);
        static::assertStringContainsString('data-validation="email"', $optional);
    }

    public function testInputLinksDescriptionAndFeedbackToTheControl(): void
    {
        $html = $this->render('Sw:Form:Input', [
            'name' => 'password',
            'label' => 'Password',
            'description' => 'At least 8 characters.',
        ]);

        static::assertStringContainsString('aria-describedby="password-description password-feedback"', $html);
        static::assertStringContainsString('class="sw-form-description form-text" id="password-description"', $html);
        static::assertStringContainsString('At least 8 characters.', $html);
    }

    public function testInputOmitsTheLabelElementWhenNoLabelIsGiven(): void
    {
        $html = $this->render('Sw:Form:Input', ['name' => 'firstName']);

        static::assertStringNotContainsString('<label', $html);
        static::assertStringContainsString('<input', $html);
    }

    public function testInputUsesTheAriaLabelWhenThereIsNoVisibleLabel(): void
    {
        $html = $this->render('Sw:Form:Input', [
            'name' => 'search',
            'ariaLabel' => 'Search term',
        ]);

        static::assertStringNotContainsString('<label', $html);
        static::assertStringContainsString('aria-label="Search term"', $html);
    }

    public function testInputMarksTheControlInvalid(): void
    {
        $html = $this->render('Sw:Form:Input', [
            'name' => 'email',
            'label' => 'Email',
            'violationPath' => '/email',
            'isInvalid' => true,
        ]);

        static::assertStringContainsString('class="sw-form-input__control sw-form-field__control form-control is-invalid"', $html);

        // The container has to be there even without messages, otherwise client validation has
        // nowhere to write to.
        static::assertStringContainsString('id="email-feedback"', $html);
    }

    public function testInputSendsPlainAttributesToTheGroupAndPrefixedOnesToTheControl(): void
    {
        $html = $this->render('Sw:Form:Input', [
            'name' => 'firstName',
            'class' => 'col-sm-6',
            'data-group-hook' => 'group',
            'control:data-form-validation-equal' => 'passwordMatch',
        ]);

        static::assertStringContainsString('class="sw-form-input sw-form-field form-group col-sm-6"', $html);
        static::assertMatchesRegularExpression('/<div[^>]*data-group-hook="group"/', $html);
        static::assertDoesNotMatchRegularExpression('/<input[^>]*data-group-hook/', $html);

        static::assertMatchesRegularExpression('/<input[^>]*data-form-validation-equal="passwordMatch"/', $html);
        static::assertDoesNotMatchRegularExpression('/<div[^>]*data-form-validation-equal/', $html);
    }

    public function testInputAppliesTheAdditionalClassProps(): void
    {
        $html = $this->render('Sw:Form:Input', [
            'name' => 'firstName',
            'additionalClass' => 'col-sm-6',
            'additionalInputClass' => 'is--custom',
        ]);

        static::assertStringContainsString('class="sw-form-input sw-form-field form-group col-sm-6"', $html);
        static::assertStringContainsString('class="sw-form-input__control sw-form-field__control form-control is--custom"', $html);
    }

    public function testInputRendersBooleanAttributesWithoutAValue(): void
    {
        $html = $this->render('Sw:Form:Input', [
            'name' => 'firstName',
            'disabled' => true,
            'readonly' => true,
        ]);

        static::assertStringContainsString(' disabled', $html);
        static::assertStringContainsString(' readonly', $html);
        static::assertStringNotContainsString('disabled="', $html);
    }

    public function testInputOmitsOptionalAttributesThatWereNotPassed(): void
    {
        $html = $this->render('Sw:Form:Input', ['name' => 'firstName']);

        static::assertStringNotContainsString('placeholder=', $html);
        static::assertStringNotContainsString('autocomplete=', $html);
        static::assertStringNotContainsString('minlength=', $html);
        static::assertStringNotContainsString('maxlength=', $html);
        static::assertStringNotContainsString('value=', $html);
        static::assertStringNotContainsString('data-validation=', $html);
    }

    /**
     * The predecessor dropped `value` for anything `empty()` considers falsy, which silently
     * blanked a "0" on re-render after a failed submit.
     */
    public function testInputKeepsAZeroValue(): void
    {
        $html = $this->render('Sw:Form:Input', [
            'name' => 'quantity',
            'type' => 'number',
            'value' => 0,
        ]);

        static::assertStringContainsString('value="0"', $html);
    }

    public function testInputDerivesTheIdFromTheName(): void
    {
        $html = $this->render('Sw:Form:Input', [
            'name' => 'firstName',
            'label' => 'First name',
        ]);

        static::assertStringContainsString('id="firstName"', $html);
        static::assertStringContainsString('for="firstName"', $html);

        $explicit = $this->render('Sw:Form:Input', [
            'name' => 'firstName',
            'id' => 'billingFirstName',
            'label' => 'First name',
        ]);

        static::assertStringContainsString('id="billingFirstName"', $explicit);
        static::assertStringContainsString('for="billingFirstName"', $explicit);
        static::assertStringContainsString('aria-describedby="billingFirstName-feedback"', $explicit);
    }

    public function testTextareaRendersTheValueAsItsContent(): void
    {
        $html = $this->render('Sw:Form:Textarea', [
            'name' => 'content',
            'label' => 'Your review',
            'value' => 'Great product',
            'rows' => 4,
            'validationRules' => 'required,minLength',
            'minlength' => 40,
        ]);

        static::assertStringContainsString('rows="4"', $html);
        static::assertStringContainsString('minlength="40"', $html);
        static::assertStringContainsString('data-validation="required,minLength"', $html);
        static::assertStringContainsString('>Great product</textarea>', $html);
        static::assertStringContainsString('class="sw-form-textarea__control sw-form-field__control form-control"', $html);
    }

    public function testTextareaRendersEmptyWithoutAValue(): void
    {
        $html = $this->render('Sw:Form:Textarea', ['name' => 'content']);

        static::assertStringContainsString('></textarea>', $html);
    }

    public function testSelectRendersOptionsAndMarksTheSelectedOne(): void
    {
        $html = $this->render('Sw:Form:Select', [
            'name' => 'salutationId',
            'label' => 'Salutation',
            'placeholder' => 'Please choose',
            'value' => 'ms',
            'options' => [
                ['value' => 'mr', 'label' => 'Mr.'],
                ['value' => 'ms', 'label' => 'Ms.'],
                ['value' => 'nd', 'label' => 'Not specified', 'disabled' => true],
            ],
        ]);

        static::assertStringContainsString('class="sw-form-select__control sw-form-field__control form-select"', $html);
        static::assertStringContainsString('<option value="">Please choose</option>', $html);
        static::assertStringContainsString('<option value="mr">Mr.</option>', $html);
        static::assertStringContainsString('<option value="ms" selected="selected">Ms.</option>', $html);
        static::assertStringContainsString('<option value="nd" disabled>Not specified</option>', $html);
        static::assertSame(1, substr_count($html, 'selected="selected"'));
    }

    public function testSelectPreselectsThePlaceholderWhenThereIsNoValue(): void
    {
        $html = $this->render('Sw:Form:Select', [
            'name' => 'salutationId',
            'placeholder' => 'Please choose',
            'options' => [['value' => 'mr', 'label' => 'Mr.']],
        ]);

        static::assertStringContainsString('<option value="" selected="selected">Please choose</option>', $html);
    }

    public function testCheckboxUsesTheBootstrapFormCheckStructure(): void
    {
        $html = $this->render('Sw:Form:Checkbox', [
            'name' => 'acceptedDataProtection',
            'label' => 'I have read the data protection information.',
            'checked' => true,
            'validationRules' => 'required',
        ]);

        static::assertStringContainsString('class="sw-form-checkbox sw-form-field form-group form-check"', $html);
        static::assertStringContainsString('class="sw-form-checkbox__control sw-form-field__control form-check-input"', $html);
        static::assertStringContainsString('type="checkbox"', $html);
        static::assertStringContainsString('value="1"', $html);
        static::assertStringContainsString(' checked', $html);
        static::assertStringContainsString('class="sw-form-label form-check-label" for="acceptedDataProtection"', $html);

        // `custom-control-label` is a Bootstrap 4 class that no longer exists in Bootstrap 5.
        static::assertStringNotContainsString('custom-control-label', $html);
    }

    public function testCheckboxRendersTheSwitchVariant(): void
    {
        $html = $this->render('Sw:Form:Checkbox', [
            'name' => 'newsletter',
            'label' => 'Subscribe',
            'variant' => 'switch',
        ]);

        static::assertStringContainsString('class="sw-form-checkbox sw-form-field form-group form-check form-switch"', $html);
        static::assertStringContainsString('role="switch"', $html);
    }

    public function testRadioGroupWrapsTheOptionsInAFieldset(): void
    {
        $html = $this->render('Sw:Form:RadioGroup', [
            'name' => 'sizeChoice',
            'label' => 'Choose a size',
            'value' => 'md',
            'validationRules' => 'required',
            'options' => [
                ['value' => 'sm', 'label' => 'Small'],
                ['value' => 'md', 'label' => 'Medium'],
            ],
        ]);

        static::assertStringContainsString('<fieldset ', $html);
        static::assertStringContainsString('class="sw-form-radio-group sw-form-field form-radio-group mb-3"', $html);
        static::assertStringContainsString('class="sw-form-fieldset-label form-label sw-form-radio-group__legend fs-5 fw-bold"', $html);
        static::assertStringContainsString('Choose a size', $html);
        static::assertSame(1, substr_count($html, 'form-required-label'));

        static::assertSame(2, substr_count($html, 'type="radio"'));
        static::assertSame(2, substr_count($html, 'name="sizeChoice"'));
        static::assertStringContainsString('id="sizeChoice-sm"', $html);
        static::assertStringContainsString('id="sizeChoice-md"', $html);

        // Every radio points at the single feedback element of the group.
        static::assertSame(2, substr_count($html, 'aria-describedby="sizeChoice-feedback"'));
        static::assertStringContainsString('id="sizeChoice-feedback"', $html);

        static::assertSame(1, substr_count($html, ' checked'));
        static::assertMatchesRegularExpression('/id="sizeChoice-md"[^>]* checked/', $html);
    }

    /**
     * The predecessor printed the marker into every legend, required or not.
     */
    public function testRadioGroupLeavesOutTheRequiredMarkerWhenNothingIsRequired(): void
    {
        $html = $this->render('Sw:Form:RadioGroup', [
            'name' => 'sizeChoice',
            'label' => 'Choose a size',
            'options' => [['value' => 'sm', 'label' => 'Small']],
        ]);

        static::assertStringNotContainsString('form-required-label', $html);
    }

    public function testRadioRendersStandaloneWithItsOwnFormCheck(): void
    {
        $html = $this->render('Sw:Form:Radio', [
            'name' => 'sizeChoice',
            'value' => 'sm',
            'label' => 'Small',
            'inline' => true,
        ]);

        static::assertStringContainsString('class="sw-form-radio form-check mb-2 form-check-inline"', $html);
        static::assertStringContainsString('class="sw-form-radio__control sw-form-field__control form-check-input"', $html);

        // A standalone radio has no feedback element of its own, so it must not point at one.
        // Sw:Form:RadioGroup passes the id of its own feedback element down instead.
        static::assertStringNotContainsString('aria-describedby', $html);
        static::assertStringContainsString('class="sw-form-label form-check-label" for="sizeChoice-sm"', $html);
    }

    public function testBirthdaySelectRendersThreeAutocompletedSelects(): void
    {
        $html = $this->render('Sw:Form:BirthdaySelect', [
            'day' => 24,
            'month' => 7,
            'year' => 1990,
            'validationRules' => 'required',
        ]);

        static::assertStringContainsString('<fieldset ', $html);
        static::assertStringContainsString('class="sw-form-birthday-select form-group"', $html);
        static::assertStringContainsString('class="sw-form-fieldset-label form-label"', $html);
        static::assertStringNotContainsString('<label', $html);

        static::assertStringContainsString('name="birthdayDay"', $html);
        static::assertStringContainsString('name="birthdayMonth"', $html);
        static::assertStringContainsString('name="birthdayYear"', $html);

        static::assertStringContainsString('id="birthdayDay"', $html);
        static::assertStringContainsString('id="birthdayMonth"', $html);
        static::assertStringContainsString('id="birthdayYear"', $html);

        static::assertStringContainsString('autocomplete="bday-day"', $html);
        static::assertStringContainsString('autocomplete="bday-month"', $html);
        static::assertStringContainsString('autocomplete="bday-year"', $html);

        // Each part carries its own name, because the legend alone does not distinguish them.
        static::assertSame(3, substr_count($html, 'aria-label="'));
        static::assertSame(3, substr_count($html, 'aria-required="true"'));

        static::assertStringContainsString('<option value="24" selected="selected">24</option>', $html);
        static::assertStringContainsString('<option value="7" selected="selected">7</option>', $html);
        static::assertStringContainsString('<option value="1990" selected="selected">1990</option>', $html);
        static::assertSame(3, substr_count($html, 'selected="selected"'));
    }

    public function testBirthdaySelectGivesEachPartItsOwnFeedbackElement(): void
    {
        $html = $this->render('Sw:Form:BirthdaySelect', []);

        static::assertStringContainsString('id="birthdayDay-feedback"', $html);
        static::assertStringContainsString('id="birthdayMonth-feedback"', $html);
        static::assertStringContainsString('id="birthdayYear-feedback"', $html);
    }

    public function testBirthdaySelectPrefixesNamesAndIds(): void
    {
        $html = $this->render('Sw:Form:BirthdaySelect', [
            'namePrefix' => 'billingAddress',
            'idPrefix' => 'billing-',
        ]);

        static::assertStringContainsString('name="billingAddress[birthdayDay]"', $html);
        static::assertStringContainsString('name="billingAddress[birthdayMonth]"', $html);
        static::assertStringContainsString('name="billingAddress[birthdayYear]"', $html);
        static::assertStringContainsString('id="billing-birthdayDay"', $html);
        static::assertStringContainsString('id="billing-birthdayYear"', $html);
    }

    public function testBirthdaySelectCoversTheConfiguredYearRange(): void
    {
        $currentYear = (int) date('Y');

        $html = $this->render('Sw:Form:BirthdaySelect', ['yearRange' => 5]);

        static::assertStringContainsString('<option value="' . $currentYear . '">', $html);
        static::assertStringContainsString('<option value="' . ($currentYear - 5) . '">', $html);
        static::assertStringNotContainsString('<option value="' . ($currentYear - 6) . '">', $html);
    }

    public function testRadioGroupLinksItsDescriptionToEveryRadio(): void
    {
        $html = $this->render('Sw:Form:RadioGroup', [
            'name' => 'sizeChoice',
            'label' => 'Choose a size',
            'description' => 'Sizes run small.',
            'options' => [
                ['value' => 'sm', 'label' => 'Small'],
                ['value' => 'md', 'label' => 'Medium'],
            ],
        ]);

        static::assertStringContainsString('id="sizeChoice-description"', $html);
        static::assertSame(2, substr_count($html, 'aria-describedby="sizeChoice-description sizeChoice-feedback"'));
    }

    /**
     * The fields have to work as children of a form component, so the content of the tag has to
     * reach the control instead of the wrapper.
     */
    public function testSelectRendersOwnOptionMarkupPassedAsContent(): void
    {
        $html = $this->renderTemplate(
            '<twig:Sw:Form:Select name="salutationId" label="Salutation"><option value="mr">Mr.</option></twig:Sw:Form:Select>'
        );

        static::assertStringContainsString('<option value="mr">Mr.</option>', $html);
        static::assertMatchesRegularExpression('/<select[^>]*>\s*<option value="mr">/', $html);
    }

    public function testTextareaTakesItsValueFromTheContent(): void
    {
        $html = $this->renderTemplate(
            '<twig:Sw:Form:Textarea name="content" label="Your review">Great product</twig:Sw:Form:Textarea>'
        );

        static::assertStringContainsString('>Great product</textarea>', $html);
    }

    /**
     * A form component has to reach every field of every type, including ones a plugin adds, with
     * a single selector. The filter components use the same pattern: a specific root class plus a
     * shared role class the parent queries for.
     */
    public function testEveryFieldTypeCarriesTheSharedFieldAndControlHooks(): void
    {
        $fields = [
            'Sw:Form:Input' => ['name' => 'firstName'],
            'Sw:Form:Textarea' => ['name' => 'content'],
            'Sw:Form:Select' => ['name' => 'country', 'options' => [['value' => 'de', 'label' => 'Germany']]],
            'Sw:Form:Checkbox' => ['name' => 'accept'],
            'Sw:Form:RadioGroup' => ['name' => 'size', 'options' => [['value' => 'sm', 'label' => 'Small']]],
        ];

        foreach ($fields as $component => $props) {
            $html = $this->render($component, $props);

            static::assertStringContainsString('sw-form-field ', $html, $component);
            static::assertStringContainsString('sw-form-field__control ', $html, $component);
            static::assertStringContainsString('sw-form-feedback ', $html, $component);
        }
    }

    /**
     * Mapping a server violation back to its field is only possible if the field says which path it
     * answers for. Nothing else in the markup carries that.
     */
    public function testFieldExposesItsViolationPath(): void
    {
        $withPath = $this->render('Sw:Form:Input', [
            'name' => 'email',
            'violationPath' => '/email',
        ]);

        $withoutPath = $this->render('Sw:Form:Input', ['name' => 'email']);

        static::assertMatchesRegularExpression('/<div[^>]*data-violation-path="\/email"/', $withPath);
        static::assertStringNotContainsString('data-violation-path', $withoutPath);
    }

    /**
     * The group is the field, the radios are its controls — a form component must not treat the
     * individual radios as fields with feedback of their own.
     */
    public function testRadioGroupIsOneFieldWithSeveralControls(): void
    {
        $html = $this->render('Sw:Form:RadioGroup', [
            'name' => 'sizeChoice',
            'violationPath' => '/sizeChoice',
            'options' => [
                ['value' => 'sm', 'label' => 'Small'],
                ['value' => 'md', 'label' => 'Medium'],
            ],
        ]);

        static::assertSame(1, substr_count($html, 'sw-form-field '));
        static::assertSame(2, substr_count($html, 'sw-form-field__control '));
        static::assertSame(1, substr_count($html, 'sw-form-feedback '));
        static::assertSame(1, substr_count($html, 'data-violation-path="/sizeChoice"'));
        static::assertStringNotContainsString('sw-form-radio sw-form-field ', $html);
    }

    /**
     * The birthday is three independent fields the server validates separately, not one field, so
     * the surrounding fieldset must not answer for a violation path of its own.
     */
    public function testBirthdaySelectIsACompositeOfThreeFields(): void
    {
        $html = $this->render('Sw:Form:BirthdaySelect', []);

        static::assertSame(3, substr_count($html, 'sw-form-field '));
        static::assertStringNotContainsString('sw-form-birthday-select sw-form-field ', $html);

        static::assertStringContainsString('data-violation-path="/birthdayDay"', $html);
        static::assertStringContainsString('data-violation-path="/birthdayMonth"', $html);
        static::assertStringContainsString('data-violation-path="/birthdayYear"', $html);
    }

    /**
     * A hidden input has nothing to label, describe or report errors for, so it drops the whole
     * field shell instead of rendering an empty one.
     */
    public function testHiddenTypeRendersNothingButTheInput(): void
    {
        $html = $this->render('Sw:Form:Input', [
            'name' => 'forwardTo',
            'type' => 'hidden',
            'value' => 'frontend.product.reviews',
        ]);

        static::assertStringStartsWith('<input ', $html);
        static::assertStringContainsString('type="hidden"', $html);
        static::assertStringContainsString('name="forwardTo"', $html);
        static::assertStringContainsString('value="frontend.product.reviews"', $html);

        static::assertStringNotContainsString('form-group', $html);
        static::assertStringNotContainsString('<label', $html);
        static::assertStringNotContainsString('form-field-feedback', $html);
        static::assertStringNotContainsString('aria-describedby', $html);

        // It is not a field the form component should validate or map violations onto.
        static::assertStringNotContainsString('sw-form-field', $html);
    }

    public function testHiddenTypePassesAttributesStraightToTheInput(): void
    {
        $html = $this->render('Sw:Form:Input', [
            'name' => '_grecaptcha_v3',
            'type' => 'hidden',
            'data-captcha-token' => 'v3',
        ]);

        static::assertMatchesRegularExpression('/<input[^>]*data-captcha-token="v3"/', $html);
    }

    public function testRendersServerSideViolationsIntoTheFeedbackElement(): void
    {
        $html = $this->render('Sw:Form:Input', [
            'name' => 'email',
            'label' => 'Email',
            'violationPath' => '/email',
            'formViolations' => $this->violations('/email'),
        ]);

        static::assertStringContainsString('form-control is-invalid', $html);
        static::assertStringContainsString('<div class="invalid-feedback">', $html);
        static::assertStringContainsString('Input should not be empty.', $html);

        // The message has to land inside the element the control points at.
        static::assertMatchesRegularExpression(
            '/id="email-feedback"[^>]*>\s*<div class="invalid-feedback">/',
            $html
        );
    }

    /**
     * `ConstraintViolationException::getViolations()` returns the *whole* list when the path is
     * empty, so a field marked invalid without a path would otherwise print every violation on the
     * page under itself.
     */
    public function testAFieldWithoutAViolationPathNeverPrintsAnotherFieldsViolations(): void
    {
        $html = $this->render('Sw:Form:Input', [
            'name' => 'firstName',
            'label' => 'First name',
            'isInvalid' => true,
            'formViolations' => $this->violations('/email'),
        ]);

        static::assertStringContainsString('form-control is-invalid', $html);
        static::assertStringNotContainsString('invalid-feedback', $html);
        static::assertStringNotContainsString('Input should not be empty.', $html);
    }

    public function testAFieldOnlyPrintsTheViolationsOfItsOwnPath(): void
    {
        $html = $this->render('Sw:Form:Input', [
            'name' => 'firstName',
            'label' => 'First name',
            'violationPath' => '/firstName',
            'formViolations' => $this->violations('/email'),
        ]);

        static::assertStringNotContainsString('is-invalid', $html);
        static::assertStringNotContainsString('invalid-feedback', $html);
    }

    public function testCheckboxLinksItsDescriptionToTheControl(): void
    {
        $html = $this->render('Sw:Form:Checkbox', [
            'name' => 'newsletter',
            'label' => 'Subscribe',
            'description' => 'You can unsubscribe at any time.',
        ]);

        static::assertStringContainsString('aria-describedby="newsletter-description newsletter-feedback"', $html);
        static::assertStringContainsString('id="newsletter-description"', $html);
    }

    public function testCheckboxTakesAnAriaLabelWhenItHasNoVisibleLabel(): void
    {
        $html = $this->render('Sw:Form:Checkbox', [
            'name' => 'selectAll',
            'ariaLabel' => 'Select all items',
        ]);

        static::assertStringNotContainsString('<label', $html);
        static::assertStringContainsString('aria-label="Select all items"', $html);
    }

    /**
     * WCAG 2.5.3: an aria-label replaces the accessible name, so a field that already shows a label
     * must not get a second, competing one.
     */
    public function testAriaLabelIsDroppedWhenThereIsAVisibleLabel(): void
    {
        $html = $this->render('Sw:Form:Input', [
            'name' => 'firstName',
            'label' => 'First name',
            'ariaLabel' => 'Something else',
        ]);

        static::assertStringContainsString('First name', $html);
        static::assertStringNotContainsString('aria-label', $html);
    }

    /**
     * `label` and `description` are the only outputs in the set that end in `|raw`. Legacy labels
     * carry links (privacy checkbox), so markup has to survive while scripts must not.
     */
    public function testLabelAndDescriptionAreSanitizedBeforeBeingRenderedRaw(): void
    {
        $html = $this->render('Sw:Form:Checkbox', [
            'name' => 'acceptedDataProtection',
            'label' => 'I accept the <a href="/privacy">privacy policy</a><script>alert(1)</script>',
            'description' => 'See our <a href="/terms">terms</a><script>alert(2)</script>',
        ]);

        static::assertStringContainsString('<a href="/privacy">privacy policy</a>', $html);
        static::assertStringContainsString('<a href="/terms">terms</a>', $html);
        static::assertStringNotContainsString('<script>', $html);
        static::assertStringNotContainsString('alert(1)', $html);
        static::assertStringNotContainsString('alert(2)', $html);
    }

    public function testSelectCanMakeThePlaceholderUnselectable(): void
    {
        $selectable = $this->render('Sw:Form:Select', [
            'name' => 'countryId',
            'placeholder' => 'Please choose',
            'options' => [['value' => 'de', 'label' => 'Germany']],
        ]);

        $locked = $this->render('Sw:Form:Select', [
            'name' => 'countryId',
            'placeholder' => 'Please choose',
            'placeholderDisabled' => true,
            'options' => [['value' => 'de', 'label' => 'Germany']],
        ]);

        static::assertStringContainsString('<option value="" selected="selected">Please choose</option>', $selectable);
        static::assertStringContainsString('<option value="" selected="selected" disabled>Please choose</option>', $locked);
    }

    /**
     * `required` used to reach assistive tech only: the field announced itself as required while
     * nothing enforced it, because the client validator reads `data-validation`.
     */
    public function testRequiredWithoutValidationRulesStillReachesClientValidation(): void
    {
        $html = $this->render('Sw:Form:Input', [
            'name' => 'firstName',
            'label' => 'First name',
            'required' => true,
        ]);

        static::assertStringContainsString('aria-required="true"', $html);
        static::assertStringContainsString('data-validation="required"', $html);
    }

    public function testRequiredIsPrependedToTheOtherRulesWithoutDuplicating(): void
    {
        $combined = $this->render('Sw:Form:Input', [
            'name' => 'email',
            'required' => true,
            'validationRules' => 'email',
        ]);

        $alreadyThere = $this->render('Sw:Form:Input', [
            'name' => 'email',
            'validationRules' => 'required,email',
        ]);

        static::assertStringContainsString('data-validation="required,email"', $combined);
        static::assertStringContainsString('data-validation="required,email"', $alreadyThere);
    }

    public function testRadioGroupPassesTheComputedRulesToItsRadios(): void
    {
        $html = $this->render('Sw:Form:RadioGroup', [
            'name' => 'sizeChoice',
            'required' => true,
            'options' => [['value' => 'sm', 'label' => 'Small']],
        ]);

        static::assertStringContainsString('data-validation="required"', $html);
        static::assertStringContainsString('aria-required="true"', $html);
    }

    /**
     * After a failed submit the page re-renders with `is-invalid`, which is a colour. Without
     * `aria-invalid` a screen reader is never told the field is the one at fault.
     */
    public function testServerRenderedInvalidFieldsAreAnnouncedAsInvalid(): void
    {
        $invalid = $this->render('Sw:Form:Input', [
            'name' => 'email',
            'label' => 'Email',
            'violationPath' => '/email',
            'formViolations' => $this->violations('/email'),
        ]);

        $valid = $this->render('Sw:Form:Input', ['name' => 'email', 'label' => 'Email']);

        static::assertStringContainsString('aria-invalid="true"', $invalid);
        static::assertStringNotContainsString('aria-invalid', $valid);
    }

    private function violations(string $propertyPath): ConstraintViolationException
    {
        $violation = new ConstraintViolation(
            'Input should not be empty.',
            'VIOLATION::IS_BLANK_ERROR',
            [],
            '',
            $propertyPath,
            null,
            null,
            'VIOLATION::IS_BLANK_ERROR'
        );

        return new ConstraintViolationException(new ConstraintViolationList([$violation]), []);
    }

    /**
     * @param array<string, mixed> $props
     */
    private function render(string $component, array $props): string
    {
        return $this->renderTemplate(\sprintf('{{ component(\'%s\', props) }}', $component), ['props' => $props]);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function renderTemplate(string $template, array $context = []): string
    {
        $twig = static::getContainer()->get('twig');
        static::assertInstanceOf(Environment::class, $twig);

        return trim($twig->createTemplate($template)->render($context));
    }
}
