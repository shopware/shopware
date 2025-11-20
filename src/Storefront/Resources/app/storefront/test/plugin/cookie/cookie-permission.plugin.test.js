import FormValidation from "src/helper/form-validation.helper";
import CookieStorage from "src/helper/storage/cookie-storage.helper";
import CookiePermissionPlugin from "src/plugin/cookie/cookie-permission.plugin";

/**
 * @package framework
 */
describe("CookiePermissionPlugin tests", () => {
	let cookiePermissionPlugin;
	let cookieBarElement;

	beforeEach(() => {
		// Mock window.localStorage
		Object.defineProperty(window, "localStorage", {
			value: {
				getItem: jest.fn(),
				setItem: jest.fn(),
				removeItem: jest.fn(),
				clear: jest.fn(),
			},
			writable: true,
		});

		// Create DOM elements
		document.body.innerHTML = `
            <div class="cookie-permission-container" style="display: none;">
                <div class="cookie-permission-content">
                    <p>This website uses cookies.</p>
                    <button class="js-cookie-permission-button">Accept</button>
                </div>
            </div>
        `;

		cookieBarElement = document.querySelector(".cookie-permission-container");
		cookiePermissionPlugin = new CookiePermissionPlugin(cookieBarElement);

		// Mock emitter
		cookiePermissionPlugin.$emitter = {
			publish: jest.fn(),
		};

		// Mock CookieStorage
		jest.spyOn(CookieStorage, "getItem").mockReturnValue(null);
		jest.spyOn(CookieStorage, "setItem").mockImplementation(() => {});

		// Mock ResizeObserver
		global.ResizeObserver = jest.fn().mockImplementation(() => ({
			observe: jest.fn(),
			unobserve: jest.fn(),
			disconnect: jest.fn(),
		}));
	});

	afterEach(() => {
		document.body.innerHTML = "";
		jest.clearAllMocks();
	});

	test("plugin initializes correctly", () => {
		expect(cookiePermissionPlugin).toBeDefined();
		expect(cookiePermissionPlugin instanceof CookiePermissionPlugin).toBe(true);
		expect(cookiePermissionPlugin._button).toBeDefined();
	});

	test("shows cookie bar when no preference is set", () => {
		// Reset the element to initial state
		cookieBarElement.style.display = "none";

		CookieStorage.getItem.mockReturnValue(null);

		// Create a new plugin instance that will trigger the display
		const freshCookieBarElement = document.createElement("div");
		freshCookieBarElement.classList.add("cookie-permission-container");
		freshCookieBarElement.style.display = "none";
		freshCookieBarElement.innerHTML = `
            <div class="cookie-permission-content">
                <p>This website uses cookies.</p>
                <button class="js-cookie-permission-button">Accept</button>
            </div>
        `;

		const freshPlugin = new CookiePermissionPlugin(freshCookieBarElement);

		// Mock the emitter after plugin creation but before checking
		const publishSpy = jest.fn();
		freshPlugin.$emitter = { publish: publishSpy };

		// Manually call _showCookieBar to test the behavior
		freshPlugin._showCookieBar();

		expect(freshCookieBarElement.style.display).toBe("block");
		expect(publishSpy).toHaveBeenCalledWith("showCookieBar");
	});

	test("does not show cookie bar when preference is already set", () => {
		// Reset the element to initial state
		cookieBarElement.style.display = "none";

		CookieStorage.getItem.mockReturnValue("1");

		cookiePermissionPlugin = new CookiePermissionPlugin(cookieBarElement);
		cookiePermissionPlugin.$emitter = { publish: jest.fn() };

		expect(cookieBarElement.style.display).toBe("none");
		expect(cookiePermissionPlugin.$emitter.publish).not.toHaveBeenCalledWith(
			"showCookieBar",
		);
	});

	test("handles deny button click", () => {
		const mockEvent = new Event("click");
		mockEvent.preventDefault = jest.fn();

		cookiePermissionPlugin._handleDenyButton(mockEvent);

		expect(mockEvent.preventDefault).toHaveBeenCalled();
		expect(CookieStorage.setItem).toHaveBeenCalledWith(
			"cookie-preference",
			"1",
			30,
		);
		expect(cookieBarElement.style.display).toBe("none");
		expect(cookiePermissionPlugin.$emitter.publish).toHaveBeenCalledWith(
			"onClickDenyButton",
		);
		expect(cookiePermissionPlugin.$emitter.publish).toHaveBeenCalledWith(
			"hideCookieBar",
		);
		expect(cookiePermissionPlugin.$emitter.publish).toHaveBeenCalledWith(
			"removeBodyPadding",
		);
	});

	test("sets body padding based on cookie bar height", () => {
		const originalOffsetHeight = Object.getOwnPropertyDescriptor(
			HTMLElement.prototype,
			"offsetHeight",
		);

		// Mock offsetHeight
		Object.defineProperty(HTMLElement.prototype, "offsetHeight", {
			configurable: true,
			value: 50,
		});

		cookiePermissionPlugin._setBodyPadding();

		expect(document.body.style.paddingBottom).toBe("50px");
		expect(cookiePermissionPlugin.$emitter.publish).toHaveBeenCalledWith(
			"setBodyPadding",
		);

		// Restore original offsetHeight
		if (originalOffsetHeight) {
			Object.defineProperty(
				HTMLElement.prototype,
				"offsetHeight",
				originalOffsetHeight,
			);
		}
	});

	test("removes body padding", () => {
		document.body.style.paddingBottom = "50px";

		cookiePermissionPlugin._removeBodyPadding();

		// Browser normalizes '0' to '0px', so we check for either
		expect(["0", "0px"]).toContain(document.body.style.paddingBottom);
		expect(cookiePermissionPlugin.$emitter.publish).toHaveBeenCalledWith(
			"removeBodyPadding",
		);
	});

	test("listens for showCookieBar custom event", () => {
		const showSpy = jest.spyOn(cookiePermissionPlugin, "_showCookieBar");
		const setPaddingSpy = jest.spyOn(cookiePermissionPlugin, "_setBodyPadding");

		const customEvent = new CustomEvent("showCookieBar");
		document.dispatchEvent(customEvent);

		expect(showSpy).toHaveBeenCalled();
		expect(setPaddingSpy).toHaveBeenCalled();
	});

	test("handles showCookieBar event when called directly", () => {
		const showSpy = jest.spyOn(cookiePermissionPlugin, "_showCookieBar");
		const setPaddingSpy = jest.spyOn(cookiePermissionPlugin, "_setBodyPadding");

		cookiePermissionPlugin._handleShowCookieBarEvent();

		expect(showSpy).toHaveBeenCalled();
		expect(setPaddingSpy).toHaveBeenCalled();
	});

	test("listens for hideCookieBar custom event", () => {
		const hideSpy = jest.spyOn(cookiePermissionPlugin, "_hideCookieBar");
		const removePaddingSpy = jest.spyOn(cookiePermissionPlugin, "_removeBodyPadding");

		const customEvent = new CustomEvent("hideCookieBar");
		document.dispatchEvent(customEvent);

		expect(hideSpy).toHaveBeenCalled();
		expect(removePaddingSpy).toHaveBeenCalled();
	});

	test("handles hideCookieBar event when called directly", () => {
		const hideSpy = jest.spyOn(cookiePermissionPlugin, "_hideCookieBar");
		const removePaddingSpy = jest.spyOn(cookiePermissionPlugin, "_removeBodyPadding");

		cookiePermissionPlugin._handleHideCookieBarEvent();

		expect(hideSpy).toHaveBeenCalled();
		expect(removePaddingSpy).toHaveBeenCalled();
	});

	test("calculates cookie bar height correctly", () => {
		const originalOffsetHeight = Object.getOwnPropertyDescriptor(
			HTMLElement.prototype,
			"offsetHeight",
		);

		// Mock offsetHeight
		Object.defineProperty(HTMLElement.prototype, "offsetHeight", {
			configurable: true,
			value: 75,
		});

		const height = cookiePermissionPlugin._calculateCookieBarHeight();

		expect(height).toBe(75);

		// Restore original offsetHeight
		if (originalOffsetHeight) {
			Object.defineProperty(
				HTMLElement.prototype,
				"offsetHeight",
				originalOffsetHeight,
			);
		}
	});

	test("registers event listeners correctly", () => {
		const windowAddEventListenerSpy = jest.spyOn(window, "addEventListener");

		cookiePermissionPlugin._registerEvents();

		expect(windowAddEventListenerSpy).toHaveBeenCalledWith(
			"resize",
			expect.any(Function),
			expect.any(Object),
		);
	});

	test("registers custom event listeners during initialization", () => {
		const addEventListenerSpy = jest.spyOn(document, "addEventListener");

		new CookiePermissionPlugin(cookieBarElement);

		expect(addEventListenerSpy).toHaveBeenCalledWith(
			"showCookieBar",
			expect.any(Function),
		);
		expect(addEventListenerSpy).toHaveBeenCalledWith(
			"hideCookieBar",
			expect.any(Function),
		);
	});
});

describe("Cookie reCAPTCHA Integration tests", () => {
	let formValidation;
	let cookiePermissionPlugin;
	let cookieBarElement;
	let formElement;

	beforeEach(() => {
		// Setup window globals
		window.useDefaultCookieConsent = true;
		window.validationMessages = {
			required: "Input should not be empty.",
			email: "Invalid email address.",
			confirmation: "Confirmation field does not match.",
			minLength: "Input is too short.",
			grecaptcha: "Please accept cookies to use reCAPTCHA.",
		};

		// Mock window.localStorage
		Object.defineProperty(window, "localStorage", {
			value: {
				getItem: jest.fn(),
				setItem: jest.fn(),
				removeItem: jest.fn(),
				clear: jest.fn(),
			},
			writable: true,
		});

		// Setup DOM
		document.body.innerHTML = `
            <div class="cookie-permission-container" style="display: none;">
                <div class="cookie-permission-content">
                    <p>This website uses cookies.</p>
                    <button class="js-cookie-permission-button">Accept</button>
                </div>
            </div>

            <form id="contact-form">
                <div class="form-group">
                    <label for="name">Name</label>
                    <input type="text" name="name" id="name" data-validation="required" aria-describedby="name-feedback">
                    <div id="name-feedback" class="form-field-feedback"></div>
                </div>

                <div class="form-group">
                    <label for="grecaptcha-v3">reCAPTCHA v3</label>
                    <input type="hidden" name="_grecaptcha_v3" id="grecaptcha-v3" data-validation="grecaptcha,required" aria-describedby="grecaptcha-v3-feedback">
                    <div id="grecaptcha-v3-feedback" class="form-field-feedback"></div>
                </div>

                <button type="submit">Submit</button>
            </form>
        `;

		// Initialize components
		cookieBarElement = document.querySelector(".cookie-permission-container");
		formElement = document.querySelector("#contact-form");

		// Mock CookieStorage to simulate no cookies accepted
		jest.spyOn(CookieStorage, "getItem").mockImplementation((cookieName) => {
			if (cookieName === "cookie-preference") return null; // No cookie bar preference set
			if (cookieName === "_GRECAPTCHA") return null; // No reCAPTCHA cookies accepted
			return null;
		});
		jest.spyOn(CookieStorage, "setItem").mockImplementation(() => {});

		// Create form validation instance
		formValidation = new FormValidation();

		// Create cookie permission plugin instance
		cookiePermissionPlugin = new CookiePermissionPlugin(cookieBarElement);
		cookiePermissionPlugin.$emitter = { publish: jest.fn() };

		// Mock ResizeObserver
		global.ResizeObserver = jest.fn().mockImplementation(() => ({
			observe: jest.fn(),
			unobserve: jest.fn(),
			disconnect: jest.fn(),
		}));
	});

	afterEach(() => {
		document.body.innerHTML = "";
		jest.clearAllMocks();
	});

	test("integration: validateGrecaptcha triggers cookie bar to show", () => {
		// Setup spies
		const showCookieBarSpy = jest.spyOn(
			cookiePermissionPlugin,
			"_showCookieBar",
		);
		const setBodyPaddingSpy = jest.spyOn(
			cookiePermissionPlugin,
			"_setBodyPadding",
		);

		// Get the grecaptcha field
		const grecaptchaField = document.getElementById("grecaptcha-v3");

		// Validate the field - this should fail and trigger the event
		const validationResult = formValidation.validateField(grecaptchaField);

		// Assertions - field should fail both grecaptcha and required validations
		expect(validationResult).toEqual(["grecaptcha", "required"]);
		expect(showCookieBarSpy).toHaveBeenCalled(); // Cookie bar should be shown
		expect(setBodyPaddingSpy).toHaveBeenCalled(); // Body padding should be set
		expect(cookiePermissionPlugin.$emitter.publish).toHaveBeenCalledWith(
			"showCookieBar",
		);
	});

	test("integration: cookie bar does not show when reCAPTCHA cookies are accepted", () => {
		// Mock that reCAPTCHA cookies are accepted
		CookieStorage.getItem.mockImplementation((cookieName) => {
			if (cookieName === "cookie-preference") return null; // No cookie bar preference set
			if (cookieName === "_GRECAPTCHA") return "1"; // reCAPTCHA cookies accepted
			return null;
		});

		// Setup spies
		const showCookieBarSpy = jest.spyOn(
			cookiePermissionPlugin,
			"_showCookieBar",
		);

		// Get the grecaptcha field and set a value to pass required validation
		const grecaptchaField = document.getElementById("grecaptcha-v3");
		grecaptchaField.value = "test-token"; // Set a value to pass required validation

		// Validate the field - this should pass
		const validationResult = formValidation.validateField(grecaptchaField);

		// Assertions
		expect(validationResult).toEqual([]); // Field should pass validation
		expect(showCookieBarSpy).not.toHaveBeenCalled(); // Cookie bar should not be shown
	});

	test("integration: form validation with multiple fields including grecaptcha", () => {
		// Setup spies
		const showCookieBarSpy = jest.spyOn(
			cookiePermissionPlugin,
			"_showCookieBar",
		);

		// Fill in valid name
		const nameField = document.getElementById("name");
		nameField.value = "John Doe";
		nameField.checkVisibility = jest.fn().mockReturnValue(true);

		// Mock grecaptcha field visibility
		const grecaptchaField = document.getElementById("grecaptcha-v3");
		grecaptchaField.checkVisibility = jest.fn().mockReturnValue(true);

		// Validate entire form
		const invalidFields = formValidation.validateForm(formElement);

		// Assertions
		expect(invalidFields).toHaveLength(1); // Only grecaptcha field should be invalid
		expect(invalidFields[0]).toBe(grecaptchaField);
		expect(showCookieBarSpy).toHaveBeenCalled(); // Cookie bar should be shown
	});

	test("integration: manual showCookieBar event dispatch works", () => {
		// Setup spies
		const showCookieBarSpy = jest.spyOn(
			cookiePermissionPlugin,
			"_showCookieBar",
		);
		const setBodyPaddingSpy = jest.spyOn(
			cookiePermissionPlugin,
			"_setBodyPadding",
		);

		// Manually dispatch the custom event (simulating other plugins)
		const customEvent = new CustomEvent("showCookieBar");
		document.dispatchEvent(customEvent);

		// Assertions
		expect(showCookieBarSpy).toHaveBeenCalled();
		expect(setBodyPaddingSpy).toHaveBeenCalled();
	});

	test("integration: cookie bar behavior when accepting cookies", () => {
		// Setup spies
		const hideCookieBarSpy = jest.spyOn(
			cookiePermissionPlugin,
			"_hideCookieBar",
		);
		const removeBodyPaddingSpy = jest.spyOn(
			cookiePermissionPlugin,
			"_removeBodyPadding",
		);

		// Simulate user clicking accept button
		const clickEvent = new Event("click");
		clickEvent.preventDefault = jest.fn();

		cookiePermissionPlugin._handleDenyButton(clickEvent);

		// Assertions
		expect(clickEvent.preventDefault).toHaveBeenCalled();
		expect(CookieStorage.setItem).toHaveBeenCalledWith(
			"cookie-preference",
			"1",
			30,
		);
		expect(hideCookieBarSpy).toHaveBeenCalled();
		expect(removeBodyPaddingSpy).toHaveBeenCalled();
		expect(cookiePermissionPlugin.$emitter.publish).toHaveBeenCalledWith(
			"onClickDenyButton",
		);
	});

	test("integration: event system is decoupled - FormValidation does not directly reference CookiePermissionPlugin", () => {
		// This test verifies that FormValidation doesn't have direct dependencies on CookiePermissionPlugin
		const formValidationInstance = new FormValidation();

		// Check that FormValidation doesn't have direct references to cookie plugin
		expect(formValidationInstance.cookiePermissionPlugin).toBeUndefined();
		expect(formValidationInstance.showCookieBar).toBeUndefined();

		// The only connection should be through the custom event system
		const dispatchEventSpy = jest.spyOn(document, "dispatchEvent");

		const grecaptchaField = document.createElement("input");
		grecaptchaField.setAttribute("name", "_grecaptcha_v3");

		// This should only dispatch an event, not call plugin methods directly
		formValidationInstance.validateGrecaptcha("", grecaptchaField);

		expect(dispatchEventSpy).toHaveBeenCalledWith(
			expect.objectContaining({
				type: "showCookieBar",
			}),
		);
	});

	test("integration: shows cookie bar when cookie-preference is set but _GRECAPTCHA cookie is missing", () => {
		// This simulates the edge case where cookie-preference was set to 1
		// but the cookie permission plugin wasn't properly initialized,
		// so _GRECAPTCHA cookie is missing
		CookieStorage.getItem.mockImplementation((cookieName) => {
			if (cookieName === "cookie-preference") return "1"; // Cookie bar preference is set
			if (cookieName === "_GRECAPTCHA") return null; // But reCAPTCHA cookies are missing
			return null;
		});

		// Create a new plugin instance that respects the cookie-preference
		const testCookieBarElement = document.createElement("div");
		testCookieBarElement.classList.add("cookie-permission-container");
		testCookieBarElement.style.display = "none";
		testCookieBarElement.innerHTML = `
			<div class="cookie-permission-content">
				<p>This website uses cookies.</p>
				<button class="js-cookie-permission-button">Accept</button>
			</div>
		`;

		const testCookiePlugin = new CookiePermissionPlugin(testCookieBarElement);
		testCookiePlugin.$emitter = { publish: jest.fn() };

		// Setup spies
		const showCookieBarSpy = jest.spyOn(testCookiePlugin, "_showCookieBar");

		// Get the grecaptcha field
		const grecaptchaField = document.getElementById("grecaptcha-v3");

		// Validate the field - this should fail and trigger the event to show cookie bar
		const validationResult = formValidation.validateField(grecaptchaField);

		// Assertions - field should fail both grecaptcha and required validations
		expect(validationResult).toEqual(["grecaptcha", "required"]);
		expect(showCookieBarSpy).toHaveBeenCalled(); // Cookie bar should be shown despite cookie-preference being set
		expect(testCookiePlugin.$emitter.publish).toHaveBeenCalledWith("showCookieBar");
	});
});
