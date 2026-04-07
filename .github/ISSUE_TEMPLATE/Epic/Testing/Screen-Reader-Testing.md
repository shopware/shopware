<br>

> **TL;DR: All content and functionality should be available and operable with a screen reader. [SC 4.1.2](https://www.w3.org/WAI/WCAG22/Understanding/name-role-value.html)**

This step ensures that all visual information offers a text/audible alternative and that all functionality can still be used with a screen reader. This issue should be utilised both as individual features are being implemented and towards the end, before release. It should ultimately be one of the last issues resolved.

> [!Tip]
> Do this -after- **Comprehensive Keyboard Testing** (if an interactive element is not accessible with a keyboard, it won’t be accessible to a screen reader).

## Screen Reader Basics

### NVDA (WIndows)

<details>
<summary> NVDA basic shortcut keys</summary>
<br>

Sources: [NVDA User Guide](https://download.nvaccess.org/documentation/en/userGuide.html), [Deque NVDA Cheatsheet](https://dequeuniversity.com/screenreaders/nvda-keyboard-shortcuts)

**Browse Mode:** Browse mode is used when reading documents or web pages.

**Focus Mode:** Focus mode is used when the user enters a form or other fields that require user input.

The default NVDA modifier key is either the `numpadZero`, (with `numLock` off), or the `insert` key, near the `delete`, `home` and `end` keys. The NVDA modifier key can also be set to the `capsLock` key.

It is abbreviated as `NVDA`.

| Name                      | Key                      | Description                                                                         |
| ------------------------- | ------------------------ | ----------------------------------------------------------------------------------- |
| Start NVDA                | `Ctrl`+`Alt`+`n`         | Starts or restarts NVDA                                                             |
| Exit NVDA                 | `NVDA`+`q`, then `Enter` | Exits NVDA                                                                          |
| Pause or restart speech   | `Shift`                  | Instantly pauses speech. Pressing it again will continue speaking where it left off |
| Stop speech               | `Ctrl`                   | Instantly stops speaking                                                            |
| Toggle browse/focus modes | `NVDA`+`Spacebar`        | Toggles between focus mode and browse mode                                          |
| Exit focus mode           | `Esc`                    | Switches back to browse mode if focus mode was previously switched to automatically |

The full list of Single Letter Navigation keys is in the [Browse Mode](https://download.nvaccess.org/documentation/en/userGuide.html#BrowseMode) section of the user guide.

| Command                  | Keystroke    | Description                                                                           |
| ------------------------ | ------------ | ------------------------------------------------------------------------------------- |
| Heading                  | h            | Move to the next heading                                                              |
| Heading level 1, 2, etc. | 1, 2, etc.   | Move to the next heading at the specified level                                       |
| Form field               | f            | Move to the next form field (edit box, button, etc)                                   |
| Link                     | k            | Move to the next link                                                                 |
| Landmark                 | d            | Move to the next landmark                                                             |
| List                     | l            | Move to the next list                                                                 |
| Table                    | t            | Move to the next table                                                                |
| Move backwards           | shift+letter | Press shift and any of the above letters to move to the previous element of that type |
| Elements list            | NVDA+f7      | Lists various types of elements, such as links and headings                           |

<br>

Consider enabling **Speech Viewer** under **Tools** which opens a window that shows everything NVDA states.

---

</details>

### VoiceOver (macOS)

<details>
<summary> VoiceOver basic shortcut keys</summary>
<br>

Sources: [VoiceOver User Guide](https://support.apple.com/en-gb/guide/voiceover/welcome/mac), [Deque VoiceOver Cheatsheet](https://dequeuniversity.com/screenreaders/voiceover-keyboard-shortcuts)

To trigger a voice over command, press `Control`+`Option`.
`Control`+`Option` are often abbreviated with `VO`.

| Shortcut        | Action                                                                                                                                                                                                                                                                                                                                              |
| --------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| ⌘ + F5          | Turn on VoiceOver                                                                                                                                                                                                                                                                                                                                   |
| `Control`       | Stop reading                                                                                                                                                                                                                                                                                                                                        |
| `VO` + `←/→`    | Basic Navigation                                                                                                                                                                                                                                                                                                                                    |
| `VO`+`u`        | Opens the rotor for an alternative way to navigate.<br>Use `←/→` Arrows to navigate element types, `↑/↓` for individual options and `Enter` to select.<br><br>You can choose which element types are viewable in the Rotor by opening the VoiceOver Utility with `VO`+`F8`, then going to `Web` > `Web Rotor`.<br><br>Exit the rotor with `Esc`<br> |
| `VO`+`Spacebar` | Activate a link/button/click mouse                                                                                                                                                                                                                                                                                                                  |

---

</details>

#### Additional Resources

By default, semantic HTML elements will reliably work when implemented correctly.

For more complex cases, you can cross reference our component libraries ([Bootstrap](https://getbootstrap.com/docs/5.3/components/accordion/) | [Meteor](https://meteor.shopware.com/?path=/docs/components-form-mt-action-menu--docs)) with the [patterns and examples](https://www.w3.org/WAI/ARIA/apg/patterns/) from the ARIA Authoring Practices Guide (with the caveat that the [WCAG](https://www.w3.org/WAI/WCAG22/quickref/?currentsidebar=%23col_overview) and [APG](https://www.w3.org/WAI/ARIA/apg/) are the final authorities). Additionally, [MagentaA11y](https://www.magentaa11y.com/#/web-criteria/component/overview) has a good overview for individual components on what should be read and when.
<br>

## How to Test

### Configuration

**NVDA** must be tested with **Firefox** or **Chrome**.
**VoiceOver** must be tested with **Safari**. Other browsers may cause unexpected behaviour.

### General

The overarching requirements regardless of feature design or individual elements.

-   [ ] Screen reader is reading in the correct language
-   [ ] Screen reader can navigate content between headings, landmarks and skip links
-   [ ] All content is correctly read (minus elements explicitly marked as decorative, which should be skipped)
-   [ ] All content is read in a logical order (more or less the same as visually presented)
-   [ ] All elements correctly self-identify (including dynamic grouped elements such as dialogs, listboxes, etc.), have a name that matches the visible text, and state is given where applicable (checkboxes, radio buttons, disabled buttons, etc.)
-   [ ] All link destinations are expected, no links have the same description but different destinations
-   [ ] All functionality can be completed solely via screen reader

### Content Changes

Content that appears, disappears, or has changed needs to be audibly conveyed.

-   [ ] The screenreader notifies you when content is created, deleted, or has otherwise changed (e.g a promotion is applied to your cart, a product is added to the wishlist, a product is removed from your cart, etc.)

### Errors and Notifications

Unexpected events and issues need to be brought to the users attention, including instructions on what to do

-   [ ] For general alerts and notifications, the screen reader announces them to the user (but does not move focus)
-   [ ] When validation errors occur, the screen reader conveys to the user that there is an error, which field caused the error and how to fix it

<br>
