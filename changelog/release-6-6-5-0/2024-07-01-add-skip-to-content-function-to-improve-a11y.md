---
title: Add skip to content link to improve a11y
issue: NEXT-26705
---
# Storefront
* Added new block `base_body_skip_to_content` to `Resources/views/storefront/base.html.twig`
___
# Upgrade Information
## Add skip to content link to improve a11y
The `base.html.twig` template now has a new block `base_body_skip_to_content` directly after the opening `<body>` tag.
The new block holds a link that allows to skip the focus directly to the `<main>` content element.
This improves a11y because a keyboard or screen-reader user does not have to "skip" through all elements of the page (header, top-bar) and can jump straight to the main content if wanted.
The "skip to main content" link will not be visible, unless it has focus.

```html
<body>
    <div class="skip-to-content bg-primary-subtle text-primary-emphasis visually-hidden-focusable overflow-hidden">
        <div class="container d-flex justify-content-center">
            <a href="#content-main" class="skip-to-content-link d-inline-flex text-decoration-underline m-1 p-2 fw-bold gap-2">
                Skip to main content
            </a>
        </div>
    </div>

    <main id="content-main">
        <!-- Main content... -->
    </main>
```
