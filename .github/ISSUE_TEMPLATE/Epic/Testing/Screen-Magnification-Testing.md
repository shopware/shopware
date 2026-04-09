<br>

> **TL;DR: Responsive design needs to accommodate both text and content scaling in addition to screen sizes.**

This issue focuses on specifically on Text Resizing ([SC 1.4.4](https://www.w3.org/WAI/WCAG22/Understanding/resize-text.html)) and Reflow ([SC 1.4.10](https://www.w3.org/WAI/WCAG22/Understanding/reflow)) . In a nutshell, scaling either just the browser text or all browser content should _never_ result in a loss of information or functionality. As a general rule, horizontal scrolling should be minimal, and if it is necessary, confined to the individual components.

This issue should be utilised both as individual features are being implemented and towards the end, before release. It should ultimately be one of the last issues resolved.

## How to test

### Configuration

This is easiest to test in **Firefox**

1. Set the viewport to `320px` wide
2. In the main browser menu, under `View` > `Zoom` toggle `Zoom Text Only`
3. Zoom to 200%

### General

The overarching requirements regardless of feature design or individual elements:

-   [ ] No horizontal scrollbars appear/are suddenly necessary to navigate the page, itself
-   [ ] Text wraps appropriately and is readable
-   [ ] No content is truncated or permanently blocking other content from view
-   [ ] No content is lost
-   [ ] No functionality is lost

### Individual Components

The following scenarios may become relevant depending on which components you are testing within your feature:

-   [ ] For components that require or benefit from horizontal scrolling (e.g. tables, carousels, similar), the individual panels/columns/cards fit within the '320px` viewport (see: [Technique G225](https://www.w3.org/WAI/WCAG22/Techniques/general/G225))
-   [ ] All offscreen content can still be brought into view (e.g. table columns hidden by a horizontal scrollbar)​
-   [ ] It should never be possible to have simultaneous horizontal and vertical scrolling unless explicitly covered in the [Reflow](https://www.w3.org/WAI/WCAG22/Understanding/reflow) exceptions

<br>
