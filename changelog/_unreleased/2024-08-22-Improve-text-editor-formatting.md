---
title: Improved text editor formatting
issue: NEXT-33807
---
# Administration
* Added new method `fixWrongNodes()` to `sw-text-editor` component which will wrap loose text nodes in a paragraph on enter key.
* Changed the `defaultParagraphSeparator` of the contenteditable element of the text editor to paragraph `<p>` element.
* Changed the icon name `regular-insert-row-before` in `sw-text-editor-table-toolbar.html.twig` to fix a wrong icon reference.
___
# Upgrade Information
## Improved formating behaviour of the text editor
The text editor in the administration was changed to produce paragraph `<p>` elements for new lines instead of `<div>` elements. This leads to a more consistent text formatting. You can still create `<div>` elements on purpose via using the code editor.

In addition, loose text nodes will be wrapped in a paragraph `<p>` element on initializing a new line via the enter key. In the past it could happen that when starting to write in an empty text editor, that text is not wrapped in a proper section element. Now this is automatically fixed when you add a first new line to your text. From then on everything is wrapped in paragraph elements and every new line will also create a new paragraph instead of `<div>` elements.
