<br>

> **TL;DR: Whatever you can see or do with a mouse or touch gesture, you need to be able to see and do with a keyboard. [SC 2.1.1.](https://www.w3.org/WAI/WCAG22/Understanding/keyboard.html)**

This issue focuses on specifically on [Guideline 2.1 Keyboard Accessibility](https://www.w3.org/WAI/WCAG22/Understanding/keyboard-accessible) but includes overlapping guidelines and success criteria. Additionally, keyboard interactions must follow the pre-existing patterns defined by the browser, first, and our component libraries, second (with the caveat that the [WCAG](https://www.w3.org/WAI/WCAG22/quickref/) is the final authority).

This issue should be utilised both as individual features are being implemented and towards the end, before release. It should ultimately be one of the last issues resolved.

## Keyboard basics

Sources: [WebAim](https://webaim.org/techniques/keyboard/#testing) | [MagentaA11y](https://www.magentaa11y.com/#/how-to-test-criteria/test-type/keyboard-&-focus)

| Key                   | Interaction                                                       | Notes                                                                                                                           |
| --------------------- | ----------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------- |
| `Tab` / `Shift + Tab` |  navigate forwards/backwards                                      | Keyboard focus indicators must be present. Navigation order should be logical and intuitive.                                    |
| `Enter`               | activates links and buttons                                       | Ensure elements with ARIA `role="button"` can be activated with both `Enter` and `Spacebar`.                                    |
| `Spacebar`            | activates buttons, interacts with form elements or scroll by page | The space bar will, by default, scroll the page, but only if an interactive control that allows space bar input is not focused. |
| `↑/↓` / `←/→`         | interact with form fields or scroll page vertically/horizontally  | Horizontal scrolling within the page should be minimized.                                                                       |

### Browser-defined interactions

The browser already contains a number of standard patterns for keyboard interactions. As a general rule, these patterns should not be overridden.

<details>
<summary> Most common interactions</summary>
<br>

Source: [WebAim](https://webaim.org/techniques/keyboard/#testing)

| Element                       | Keystrokes                                                                                                                                                                                                               | Notes                                                                                                                                                                                                                     |
| ----------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --- |
| Link                          | `Enter` - activate the link                                                                                                                                                                                              |                                                                                                                                                                                                                           |
| Button                        | `Enter` or `Spacebar` - activate the button                                                                                                                                                                              | Ensure elements with ARIA `role="button"` can be activated with both key commands.                                                                                                                                        |
| Checkbox                      | `Spacebar` - check/uncheck a checkbox                                                                                                                                                                                    | Users can typically select zero, one, or multiple options from group of checkboxes.                                                                                                                                       |
| Radio buttons                 | `Tab` - once to navigate into the group of radio buttons and once to navigate out of the group of radio buttons<br>`↑/↓` or `←/→` - navigate between options<br> `Spacebar`- select the focused option (if not selected) | Users can select only one option from a group of radio buttons.                                                                                                                                                           |
| Select (native dropdown) menu | `↑/↓` - navigate between options<br>`Spacebar` - expand<br>`Enter/Esc` - select option and collapse                                                                                                                      | You can also filter or jump to options in the menu as you type letters.                                                                                                                                                   |
| Autocomplete                  | Type to begin filtering<br>`↑/↓` - navigate to an option<br>`Enter` - select an option                                                                                                                                   |                                                                                                                                                                                                                           |
| Dialog                        | `Esc` - close                                                                                                                                                                                                            | Modal dialogs should maintain keyboard focus. Non-modal dialogs should not, and should close automatically when they lose focus. When a dialog closes, focus should usually return to the element that opened the dialog. |
| Slider                        | `↑/↓` or `←/→` - increase or decrease slider value <br>`Home/End` - beginning or end                                                                                                                                     | For double-headed sliders (to set a range), `Tab/Shift + Tab` should toggle between each end.In some sliders `PageUp/PageDown` can move by a larger increment (e.g., by 10%).                                             |     |
| Menu bar                      | `↑/↓` - previous/next menu option <br>`Enter` - expand the menu (optional) and select an option.<br>`←/→` - expand/collapse submenu                                                                                      | A menu bar dynamically changes content within an application. Links that utilize `Tab/Enter` are NOT menu bars.                                                                                                           |
| Tab panel                     | `Tab` - once to navigate into the group of tabs and once to navigate out of the group of tabs<br>`↑/↓` or `←/→` - choose and activate previous/next tab.                                                                 | This is for 'application' tabs that dynamically change content within the tab panel. If a menu looks like a group of tabs, but is actually a group of links to different pages, `Tab` and `Enter` are more appropriate.   |     |
| 'Tree' menu                   | `↑/↓` - navigate previous/next menu option<br>`←/→` - expand/collapse submenu, move up/down one level.                                                                                                                   |                                                                                                                                                                                                                           |

</details>
<br>

## How to Test

### Configuration

Set viewport to a width of `320px` as it is not uncommon for people to pair an external keyboard with their phones and tablets. (Sources: [WebAim](https://webaim.org/techniques/keyboard/), [Yale](https://usability.yale.edu/web-accessibility/articles/focus-keyboard-operability))

### General

The overarching requirements regardless of feature design or individual elements:

-   [ ] Interactive elements receive and display visible focus from pressing `Tab`/`Shift+Tab`, no interactive elements are missed [SC 2.4.7](https://www.w3.org/WAI/WCAG22/Understanding/focus-visible)
-   [ ] Elements receive focus in a meaningful order, reflecting more or less the order they are visually presented [SC 2.4.3](https://www.w3.org/WAI/WCAG22/Understanding/focus-order)
-   [ ] Context only changes with _explicit_ input from the user (e.g: navigating between checkboxes vs. selecting one) [SC 3.2.3](https://www.w3.org/WAI/WCAG22/Understanding/consistent-navigation)
-   [ ] Keyboard navigation _never_ gets stuck. You can always dismiss or navigate away from the current component without reloading the page or browser. [SC 2.1.2](https://www.w3.org/WAI/WCAG22/Understanding/no-keyboard-trap)
-   [ ] Skip links work for the page landmarks and any repeating content (e.g. image galleries, product sliders, etc.) [SC 2.4.1](https://www.w3.org/WAI/WCAG22/Understanding/bypass-blocks)
-   [ ] _All_ mouse functionality (including content exposed via hover) is also viewable/operable solely via keyboard [SC 1.4.13](https://www.w3.org/WAI/WCAG22/Understanding/content-on-hover-or-focus.html) | [SC 2.1.1](https://www.w3.org/WAI/WCAG22/Understanding/keyboard)
-   [ ] An item receiving keyboard focus is always partially visible and never hidden due to content (i.e. sticky footers/headers, notifications, non-modal dialogs, visual effects, etc.). Recommended to check this in combination with text zoomed to 200% [SC 2.4.11](https://www.w3.org/WAI/WCAG22/Understanding/focus-not-obscured-minimum.html)

### Individual Components

For components that are not listed in the **Browser-defined interactions**, you can check against the following libraries:

[Bootstrap](https://getbootstrap.com/docs/5.3/components/accordion/) for Storefront
[Meteor](https://meteor.shopware.com/?path=/docs/components-form-mt-action-menu--docs) for Admin

The following scenarios may become relevant depending on which components you are testing within your feature:

-   [ ] A _focused_ item is differentiated from a _selected_ item (e.g. checkboxes, radio buttons, toggles, etc.) [SC 1.4.11](https://www.w3.org/WAI/WCAG22/Understanding/non-text-contrast.html)
-   [ ] When a dialog closes, focus should usually return to the element that opened the dialog [SC 2.4.3](https://www.w3.org/WAI/WCAG22/Understanding/focus-order)
-   [ ] _All_ gesture/motion-based functionality is also viewable/operable solely via keyboard [SC 2.5.4](https://www.w3.org/WAI/WCAG22/Understanding/motion-actuation.html)
-   [ ] If a keyboard shortcut is implemented, it is _never_ accidentally triggered. Additionally, the shortcut can either be: turned off, remapped, or is only active when that component has focus [SC 2.1.4](https://www.w3.org/WAI/WCAG22/Understanding/character-key-shortcuts.html)

<br>
