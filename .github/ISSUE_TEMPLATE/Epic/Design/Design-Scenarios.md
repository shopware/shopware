This issue should be discussed after the initial drafts for necessary designs. It can also be reopened as needed.

It’s important to consider the design implications for not only different disabilities, but also different types of assistive technology. The design should be able to either adapt to the different needs, or offer an equivalent alternative (think transcripts for podcasts or closed captioning for videos). Look through the following scenarios, consider which points apply, and then evaluate with your team whether the design successfully addresses each one.

Keep this ticket open until all design iterations are finished, there are no outstanding questions or concerns, and you are confident that the design can accommodate each of the scenarios and points, below.

## Scenarios

#### 1. Sighted person using a mouth stick

- [ ] Is there any Drag and Drop, gesture, or motion-based functionality? Can it also be achieved through a single mouse click? [SC 2.5.1](https://www.w3.org/WAI/WCAG22/Understanding/pointer-gestures.html) | [SC 2.5.7](https://www.w3.org/WAI/WCAG22/Understanding/dragging-movements.html)
- [ ] For any time based events, are there controls to either adjust the timing or (ideally) disable it? [SC 2.2.1](https://www.w3.org/WAI/WCAG22/Understanding/timing-adjustable.html)

#### 2. Sighted person using voice control

- [ ] Do all interactive elements have visible text labels? [SC 2.5.3](https://www.w3.org/WAI/WCAG22/Understanding/label-in-name.html#techniques)

#### 3. Sighted person with ADHD using a screen reader

- [ ] If all graphics/colors were removed, does the design still make sense? [SC 1.3.3](https://www.w3.org/WAI/WCAG22/Understanding/sensory-characteristics.html)
- [ ] What visual content cannot be removed and will require text alternatives? [SC 1.1.1](https://www.w3.org/WAI/WCAG22/Understanding/non-text-content.html)
- [ ] Do animations and dynamic interactive content include controls to pause, stop or hide them? [SC 2.2.2](https://www.w3.org/WAI/WCAG22/Understanding/pause-stop-hide.html)

#### 4. Visually impaired person using screen magnification

- [ ] How does the page look at a viewport width of `320px`? [SC 1.4.10](https://www.w3.org/WAI/WCAG22/Understanding/reflow)
- [ ] Are there any modals, dialogs, overlays or notifications? How do they look look at a viewport width of `320px` and/or with the text scaled 200%? [SC 1.4.4](https://www.w3.org/WAI/WCAG22/Understanding/resize-text.html)
- [ ] Are there any components that would require horizontal scrolling? Are there any alternatives? [SC 1.4.10](https://www.w3.org/WAI/WCAG22/Understanding/reflow)

#### 5. Deaf person using a keyboard

- [ ] Is there content that contains audio? If yes, are there viewable text-based alternatives? [SC 1.1.1](https://www.w3.org/WAI/WCAG22/Understanding/non-text-content.html)
- [ ] Are there collections of repeating content we can add skip links to? [SC 2.4.1](https://www.w3.org/WAI/WCAG22/Understanding/bypass-blocks.html)
- [ ] Is there any content (ideally none) that is revealed on mouse hover? If so, is there an existing pattern that shows how to access it with a keyboard? [SC 1.4.13](https://www.w3.org/WAI/WCAG22/Understanding/content-on-hover-or-focus.html)

<br>
