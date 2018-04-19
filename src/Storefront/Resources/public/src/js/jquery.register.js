;(function ($) {
    'use strict';

    /**
     * Register plugin
     *
     * This plugin handles validation and addition logic for the registration form and its fields.
     */
    $.plugin('swRegister', {

        /**
         * Plugin default options.
         * Get merged automatically with the user configuration.
         */
        defaults: {

            /**
             * Class to indicate an element to be hidden.
             *
             * @property hiddenClass
             * @type {String}
             */
            hiddenClass: 'is--hidden',

            /**
             * Class to indicate that an element has an error.
             *
             * @property errorClass
             * @type {String}
             */
            errorClass: 'has--error',

            /**
             * Selector for the form.
             *
             * @property formSelector
             * @type {String}
             */
            formSelector: '.register--form',

            /**
             * Selector for the forms submit button.
             *
             * @property submitBtnSelector
             * @type {String}
             */
            submitBtnSelector: '.register--submit,.address--form-submit',

            /**
             * Selector for the type selection field.
             *
             * @property typeFieldSelector
             * @type {String}
             */
            typeFieldSelector: '.register--customertype select,.address--customertype select,.address--customertype input',

            /**
             * Type name for a company selection.
             * Used for comparison on the type selection field.
             *
             * @property companyType
             * @type {String}
             */
            companyType: 'business',

            /**
             * Selector for the skip account creation checkbox.
             * Toggles specific field sets when checked.
             *
             * @property skipAccountSelector
             * @type {String}
             */
            skipAccountSelector: '.register--check input',

            /**
             * Selector for the alternative shipping checkbox.
             * Toggles specific field sets when checked.
             *
             * @property altShippingSelector
             * @type {String}
             */
            altShippingSelector: '.register--alt-shipping input',

            /**
             * Selector for the company field set.
             *
             * @property companyFieldSelector
             * @type {String}
             */
            companyFieldSelector: '.register--company,.address--company',

            /**
             * Selector for the account field set.
             *
             * @property accountFieldSelector
             * @type {String}
             */
            accountFieldSelector: '.register--account-information',

            /**
             * Selector for the shipping field set.
             *
             * @property shippingFieldSelector
             * @type {String}
             */
            shippingFieldSelector: '.register--shipping',

            /**
             * Selector for the payment field set.
             *
             * @property paymentFieldSelector
             * @type {String}
             */
            paymentFieldSelector: '.payment--content',

            /**
             * Selector for the payment selection radio button.
             *
             * @property paymentInputSelector
             * @type {String}
             */
            paymentInputSelector: '.payment--selection-input input',

            /**
             * Selector for the country select field.
             *
             * @property countryFieldSelector
             * @type {String}
             */
            countryFieldSelector: '.select--country',

            /**
             * Selector for the state field set.
             * This corresponding field set will be toggled
             * when a country was selected.
             *
             * @property stateContainerSelector
             * @type {String}
             */
            stateContainerSelector: '.register--state-selection, .address--state-selection',

            /**
             * Selector for the payment method select fields.
             *
             * @property paymentMethodSelector
             * @type {String}
             */
            paymentMethodSelector: '.payment--method',

            /**
             * Selector for a input field.
             *
             * @property inputSelector
             * @type {String}
             */
            inputSelector: '.is--required',

            /**
             * Class that will be added to a error message.
             *
             * @property errorMessageClass
             * @type {String}
             */
            errorMessageClass: 'register--error-msg',

            /**
             * Selector for the email field.
             *
             * @property personalEmailSelector
             * @type {String}
             */
            personalEmailSelector: '#register_personal_email',

            /**
             * Selector for the password field.
             *
             * @property personalPasswordSelector
             * @type {String}
             */
            personalPasswordSelector: '#register_personal_password',

            /**
             * Selector for the email confirmation field.
             *
             * @property personalEmailConfirmationSelector
             * @type {String}
             */
            personalEmailConfirmationSelector: '#register_personal_emailConfirmation',

            /**
             * Selector for the password confirmation field.
             *
             * @property personalPasswordConfirmationSelector
             * @type {String}
             */
            personalPasswordConfirmationSelector: '#register_personal_passwordConfirmation',

            /**
             * Selector for the guest checkbox.
             *
             * @property personalPasswordConfirmationSelector
             * @type {String}
             */
            personalGuestSelector: '#register_personal_skipLogin'
        },

        /**
         * Initializes the plugin, sets up event listeners and adds the necessary
         * classes to get the plugin up and running.
         *
         * @public
         * @method init
         */
        init: function () {
            var me = this,
                opts = me.opts,
                $el = me.$el;

            me.$personalEmail = $el.find(opts.personalEmailSelector);
            me.$personalPassword = $el.find(opts.personalPasswordSelector);
            me.$personalEmailConfirmation = $el.find(opts.personalEmailConfirmationSelector);
            me.$personalPasswordConfirmation = $el.find(opts.personalPasswordConfirmationSelector);
            me.$personalGuest = $el.find(opts.personalGuestSelector);

            me.$form = $el.find(opts.formSelector);

            me.$submitBtn = $el.find(opts.submitBtnSelector);

            me.$typeSelection = $el.find(opts.typeFieldSelector);
            me.$skipAccount = $el.find(opts.skipAccountSelector);
            me.$alternativeShipping = $el.find(opts.altShippingSelector);

            me.$companyFieldset = $el.find(opts.companyFieldSelector);
            me.$accountFieldset = $el.find(opts.accountFieldSelector);
            me.$shippingFieldset = $el.find(opts.shippingFieldSelector);

            me.$countySelectFields = $el.find(opts.countryFieldSelector);

            me.$paymentMethods = $el.find(opts.paymentMethodSelector);

            me.$inputs = $el.find(opts.inputSelector);
            me.$stateContainers = $el.find(opts.stateContainerSelector);

            me.checkType();
            me.checkSkipAccount();
            me.checkChangeShipping();

            me.registerEvents();
        },

        /**
         * Registers all necessary event listeners for the plugin to proper operate.
         *
         * @public
         * @method registerEvents
         */
        registerEvents: function () {
            var me = this;

            me._on(me.$typeSelection, 'change', $.proxy(me.checkType, me));
            me._on(me.$skipAccount, 'change', $.proxy(me.checkSkipAccount, me));
            me._on(me.$alternativeShipping, 'change', $.proxy(me.checkChangeShipping, me));
            me._on(me.$countySelectFields, 'change', $.proxy(me.onCountryChanged, me));
            me._on(me.$paymentMethods, 'change', $.proxy(me.onPaymentChanged, me));
            me._on(me.$form, 'focusout', $.proxy(me.onValidateInput, me));
            me._on(me.$submitBtn, 'click', $.proxy(me.onSubmitBtn, me));

            $.publish('plugin/swRegister/onRegisterEvents', [ me ]);
        },

        /**
         * Checks the type selection field.
         * If the value is equal to the configured companyType,
         * the company field set will be shown.
         *
         * @public
         * @method checkType
         */
        checkType: function () {
            var me = this,
                opts = me.opts,
                $fieldSet = me.$companyFieldset,
                hideCompanyFields = (me.$typeSelection.length && me.$typeSelection.val() !== opts.companyType),
                requiredFields = $fieldSet.find(opts.inputSelector),
                requiredMethod = (!hideCompanyFields) ? me.setHtmlRequired : me.removeHtmlRequired,
                classMethod = (!hideCompanyFields) ? 'removeClass' : 'addClass',
                disabledMethod = (!hideCompanyFields) ? 'removeAttr' : 'attr';

            requiredMethod(requiredFields);

            $fieldSet[classMethod](opts.hiddenClass);
            $fieldSet.find('input, select, textarea')[disabledMethod]('disabled', 'disabled');

            $.publish('plugin/swRegister/onCheckType', [ me, hideCompanyFields ]);
        },

        /**
         * Checks the skip account checkbox.
         * The account field set will be shown/hidden depending
         * on the check state of the checkbox.
         *
         * @public
         * @method checkSkipAccount
         */
        checkSkipAccount: function () {
            var me = this,
                opts = me.opts,
                $fieldSet = me.$accountFieldset,
                isChecked = me.$skipAccount.is(':checked'),
                requiredFields = $fieldSet.find(opts.inputSelector),
                requiredMethod = (!isChecked) ? me.setHtmlRequired : me.removeHtmlRequired,
                classMethod = (isChecked) ? 'addClass' : 'removeClass';

            requiredMethod(requiredFields);

            $fieldSet[classMethod](opts.hiddenClass);

            $.publish('plugin/swRegister/onCheckSkipAccount', [ me, isChecked ]);
        },

        /**
         * Checks the alternative shipping checkbox.
         * The shipping field set will be shown/hidden depending
         * on the check state of the checkbox.
         *
         * @public
         * @method checkChangeShipping
         */
        checkChangeShipping: function () {
            var me = this,
                opts = me.opts,
                $fieldSet = me.$shippingFieldset,
                isChecked = me.$alternativeShipping.is(':checked'),
                requiredFields = $fieldSet.find(opts.inputSelector),
                requiredMethod = (isChecked) ? me.setHtmlRequired : me.removeHtmlRequired,
                classMethod = (isChecked) ? 'removeClass' : 'addClass';

            requiredMethod(requiredFields);

            $fieldSet[classMethod](opts.hiddenClass);

            $.publish('plugin/swRegister/onCheckChangeShipping', [ me, isChecked ]);
        },

        /**
         * Called when another country was selected in the country selection.
         * Triggers additional classes depending on the selection.
         *
         * @public
         * @method onCountryChanged
         * @param {jQuery.Event} event
         */
        onCountryChanged: function (event) {
            var me = this,
                $select = $(event.currentTarget),
                countryId = $select.val(),
                addressType = $select.attr('data-address-type'),
                $stateContainers;

            $.publish('plugin/swRegister/onCountryChangedBefore', [ me, event, countryId, addressType ]);

            me.resetStateSelections(addressType);

            $stateContainers = me.$stateContainers.filter('[data-address-type="' + addressType + '"]');

            // if there is no address type defined or no targets are found, fall back to all state containers
            if ($stateContainers.length === 0) {
                $stateContainers = me.$stateContainers;
            }

            $stateContainers = $stateContainers.filter('[data-country-id="' + countryId + '"]');

            if ($stateContainers.length) {
                $stateContainers.removeClass(me.opts.hiddenClass);
                $select = $stateContainers.find('select');
                $select.removeAttr('disabled');
            }

            $.publish('plugin/swRegister/onCountryChanged', [ me, event, countryId, addressType ]);
        },

        /**
         * Called every time the country selection changes. This method disables and hides all state selections
         * to prevent sending invalid data. The caller method needs to make sure, that the correct
         * state selection gets activated and shown again.
         *
         * @public
         * @method resetStateSelections
         * @param {String} addressType
         */
        resetStateSelections: function (addressType) {
            var me = this,
                $select,
                $stateContainers,
                $stateContainer;

            $stateContainers = me.$stateContainers.filter('[data-address-type="' + addressType + '"]');
            if ($stateContainers.length === 0) {
                $stateContainers = me.$stateContainers;
            }

            $.each($stateContainers, function(index, stateContainer) {
                $stateContainer = $(stateContainer);
                $select = $stateContainer.find('select');
                $select.attr('disabled', 'disabled');

                $stateContainer.addClass(me.opts.hiddenClass);
            });
        },

        /**
         * Called when another payment method was selected.
         * Depending on the selection, the payment field set will be toggled.
         *
         * @public
         * @method onPaymentChanged
         */
        onPaymentChanged: function () {
            var me = this,
                opts = me.opts,
                inputClass = opts.inputSelector,
                hiddenClass = opts.hiddenClass,
                inputSelector = opts.paymentInputSelector,
                paymentSelector = opts.paymentFieldSelector,
                requiredMethod,
                $fieldSet,
                isChecked,
                radio,
                $el;

            $.each(me.$paymentMethods, function (index, el) {
                $el = $(el);

                radio = $el.find(inputSelector);
                isChecked = radio[0].checked;

                requiredMethod = (isChecked) ? me.setHtmlRequired : me.removeHtmlRequired;

                requiredMethod($el.find(inputClass));

                $fieldSet = $el.find(paymentSelector);
                $fieldSet[((isChecked) ? 'removeClass' : 'addClass')](hiddenClass);
            });

            $.publish('plugin/swRegister/onPaymentChanged', [ me ]);
        },

        /**
         * Will be called when the submit button was clicked.
         * Loops through all input fields and checks if they have a value.
         * When no value is available, the field will be marked with an error.
         *
         * @public
         * @method onSubmitBtn
         */
        onSubmitBtn: function () {
            var me = this,
                $input;

            me.$inputs.each(function () {
                $input = $(this);

                if (!$input.val()) {
                    me.setFieldAsError($input);
                }
            });

            $.publish('plugin/swRegister/onSubmitButton', [ me ]);
        },

        /**
         * Called when a input field lost its focus.
         * Depending on the elements id, the corresponding method will be called.
         * billing ust id, emails and passwords will be validated via AJAX.
         *
         * @public
         * @method onValidateInput
         * @param {jQuery.Event} event
         */
        onValidateInput: function (event) {
            var me = this,
                $el = $(event.target),
                id = $el.attr('id'),
                action,
                relatedTarget = event.relatedTarget || document.activeElement;

            me.$targetElement = $(relatedTarget);

            switch (id) {
            case 'register_personal_email':
            case 'register_personal_emailConfirmation':
                action = 'ajax_validate_email';
                break;
            case 'register_billing_ustid':
                action = 'ajax_validate_billing';
                break;
            case 'register_personal_password':
            case 'register_personal_passwordConfirmation':
                action = 'ajax_validate_password';
                break;
            default:
                break;
            }

            if (!$el.val() && $el.attr('required')) {
                me.setFieldAsError($el);
            } else if ($el.attr('type') === 'checkbox' && !$el.is(':checked')) {
                me.setFieldAsError($el);
            } else if (action) {
                me.validateUsingAjax($el, action);
            } else {
                me.setFieldAsSuccess($el);
            }

            $.publish('plugin/swRegister/onValidateInput', [ me, event, action ]);
        },

        /**
         * Adds additional attributes to the given elements to indicate
         * the elements to be required.
         *
         * @private
         * @method setHtmlRequired
         * @param {jQuery} $elements
         */
        setHtmlRequired: function ($elements) {
            $elements.attr({
                'required': 'required',
                'aria-required': 'true'
            });

            $.publish('plugin/swRegister/onSetHtmlRequired', [ this, $elements ]);
        },

        /**
         * Removes addition attributes that indicate the input as required.
         *
         * @public
         * @method removeHtmlRequired
         * @param {jQuery} $inputs
         */
        removeHtmlRequired: function ($inputs) {
            $inputs.removeAttr('required aria-required');

            $.publish('plugin/swRegister/onRemoveHtmlRequired', [ this, $inputs ]);
        },

        /**
         * Adds the defined error class to the given field.
         *
         * @public
         * @method setFieldAsError
         * @param {jQuery} $el
         */
        setFieldAsError: function ($el) {
            var me = this;

            $el.addClass(me.opts.errorClass);

            $.publish('plugin/swRegister/onSetFieldAsError', [ me, $el ]);
        },

        /**
         * Removes the defined error class from the given field.
         *
         * @public
         * @method setFieldAsSuccess
         * @param {jQuery} $el
         */
        setFieldAsSuccess: function ($el) {
            var me = this;

            $el.removeClass(me.opts.errorClass);

            $.publish('plugin/swRegister/onSetFieldAsSuccess', [ me, $el ]);
        },

        /**
         * Sends an ajax request to validate a given field server side.
         *
         * @public
         * @method validateUsingAjax
         * @param {jQuery} $input
         * @param {String} action
         */
        validateUsingAjax: function ($input, action) {
            var me = this,
                data = 'action=' + action + '&' + me.$el.find('form').serialize(),
                URL = window.controller.ajax_validate + '/' + action;

            if (!URL) {
                return;
            }

            $.publish('plugin/swRegister/onValidateBefore', [ me, data, URL ]);

            $.ajax({
                'data': data,
                'type': 'post',
                'dataType': 'json',
                'url': URL,
                'success': $.proxy(me.onValidateSuccess, me, action, $input)
            });
        },

        /**
         * This method gets called when the server side validation request
         * was successfully called. Updates the corresponding fields
         * and adds/removes error messages.
         *
         * @public
         * @method onValidateSuccess
         * @param {String} action
         * @param {jQuery} $input
         * @param {Object} result
         */
        onValidateSuccess: function (action, $input, result) {
            var me = this,
                isError,
                errorMessages = [],
                skipEmailConfirmationError = me.$targetElement.attr('name') == me.$personalEmailConfirmation.attr('name') && typeof me.$personalEmailConfirmation.val() === 'undefined',
                skipPasswordConfirmationError = me.$targetElement.attr('name') == me.$personalPasswordConfirmation.attr('name') && typeof me.$personalPasswordConfirmation.val() === 'undefined';

            $('#' + action + '--message').remove();

            if (!result) {
                return;
            }

            if (skipEmailConfirmationError) {
                result['emailConfirmation'] = false;
            } else if (skipPasswordConfirmationError) {
                result['passwordConfirmation'] = false;
            }

            for (var key in result) {
                // fields with `false` are now valid
                isError = !!result[key];

                if (!isError) {
                    continue;
                }

                if (key == 'emailConfirmation' && skipEmailConfirmationError) {
                    result[key] = false;
                    continue;
                } else if (key == 'passwordConfirmation' && skipPasswordConfirmationError) {
                    result[key] = false;
                    continue;
                }

                if ($input.attr('name') == me.$personalEmailConfirmation.attr('name') || $input.attr('name') == me.$personalGuest.attr('name')) {
                    $input = me.$personalEmail;
                } else if ($input.attr('name') == me.$personalPasswordConfirmation.attr('name')) {
                    $input = me.$personalPassword;
                }

                errorMessages.push(result[key]);
            }

            if (result) {
                me.updateFieldFlags(result);
            }

            if (errorMessages && errorMessages.length) {
                $('<div>', {
                    'html': '<p>' + errorMessages.join('<br/>') + '</p>',
                    'id': action + '--message',
                    'class': me.opts.errorMessageClass
                }).insertAfter($input);

                me.setFieldAsError($input);
            }

            $.publish('plugin/swRegister/onValidateSuccess', [ me, $input ]);
        },

        /**
         * Loops through all flags and updates the error/success status
         * of the corresponding elements.
         *
         * @public
         * @method updateFieldFlags
         * @param {Object} flags
         */
        updateFieldFlags: function (flags) {
            var me = this,
                $el = me.$el,
                keys = Object.keys(flags),
                len = keys.length,
                i = 0,
                flag,
                $input;

            for (; i < len; i++) {
                flag = keys[i];
                $input = $el.find('.' + flag);

                if (flags[flag]) {
                    me.setFieldAsError($input);
                    continue;
                }

                me.setFieldAsSuccess($input);
            }

            $.publish('plugin/swRegister/onUpdateFields', [ me, flags ]);
        },

        /**
         * Destroys the initialized plugin completely, so all event listeners will
         * be removed and the plugin data, which is stored in-memory referenced to
         * the DOM node.
         *
         * @public
         * @method destroy
         */
        destroy: function () {
            this._destroy();
        }
    });
})(jQuery);
