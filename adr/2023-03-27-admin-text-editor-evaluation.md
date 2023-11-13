---
title: Admin text editor evaluation
date: 2023-03-27
area: admin
tags: [admin, text-editor, evaluation]
---

## Context
The current sw-text-editor in the administration has numerous low-level bugs in basic WYSIWYG features. It is built in a
way that is difficult to maintain and understand. Therefore, we require a new text editor that is easy to maintain, has
a good feature set, is flexible to extend and more stable.

## Decision
Building a new text editor from scratch is not a viable option. Therefore, we have evaluated various existing text 
editors and narrowed down our options to the following:

    - CKEditor 5
    - TinyMCE
    - QuillJS
    - Prosemirror
    - TipTap V2
    - Lexical

We have decided to skip CKEditor 5 and TinyMCE because they require a license for our use case and are not 100% open
source. Prosemirror was also ruled out because it provides only a low-level API and requires much more implementation 
time than the other editors. Additionally, Lexical is not a valid option for us since it is specialized for React 
environments and has no official support for VueJS.

The remaining two editors are TipTap V2 and QuillJS. Both have similar feature sets and APIs, but we have found some 
major differences between them. The first difference is that TipTap is a headless editor, which means that it only
provides the editor logic and requires a UI implementation. QuillJS, on the other hand, is a fully featured editor that
provides a UI out of the box. In our case, it is better to use a headless editor like TipTap because we can implement 
the UI to fit our needs, especially for several edge cases that are hard to implement in QuillJS.

The second major difference is in extensibility. TipTap's API is more flexible, allowing us to implement our own
features using the existing TipTap and ProseMirror plugins or build our own plugins. In most cases, the powerful main
TipTap API can already solve most of our use cases. QuillJS, on the other hand, is not as flexible as TipTap, and its
extension system is more complicated to use.

The third big difference is in stability. We found that TipTap is more stable than QuillJS, probably because TipTap is 
built on top of ProseMirror, which is a very stable and well-tested editor. Although QuillJS is also generally stable,
other developers have reported some issues with it in the past.

Our main decision driver was the extensibility of the editor. We want a text editor that is easy to extend and allows us
to implement our own features. TipTap provides a powerful extension system that is easy to use, and we can implement our
design without overwriting an existing design of other editors. Therefore, we have decided to use TipTap as the base of
our new text editor.

## Consequences
We need to replace our current sw-text-editor with the new editor. We have chosen TipTap as the base for our new text 
editor,which means that we need to implement the existing UI to the editor and implement all current features. While
some features have already been implemented during the evaluation in a short amount of time, the most challenging part 
will be to ensure backward compatibility.
