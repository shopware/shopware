# Experience Studio workspaces

A `content_workspace` stores the canvas setup independently of layout content.
Several preview frames can reference the same `content_layout`.
The Administration keeps one working draft, saved baseline, and undo history for each layout, shared by all its frames.

## Frame identity and preview state

Each entry in `configuration.frames` has its own UUID `id` and a `layoutId` referencing the shared layout.
Frame IDs are unique within a workspace; layout IDs can repeat.
The frame also stores its `x`, `y`, `width`, `height`, `viewport`, `previewEntityId`, and optional `languageId`.
The workspace stores `previewSalesChannelId` and the canvas camera (`panX`, `panY`, and `zoom`).

Frame configuration contains no layout content or unsaved editor history.
Changing a viewport, preview entity, or language changes only that frame.
The frame's actions menu selects its language without changing the Administration's global language.
The selectable languages are the active languages assigned to the selected preview sales channel.
New frames start in that channel's default language; additional previews inherit their source frame's language.
Changing the preview sales channel keeps supported frame languages and switches unsupported choices to the channel's default language.
The active frame's element settings resolve translatable properties through its selected language, that language's parent, and the system language.
Editing a translatable property writes only the selected language's entry, while the draft and undo history remain shared across previews.
Selecting an already open layout in the layout picker opens another preview of its current working draft.
Switching to a different layout clears the outgoing and incoming preview selections and closes the settings panel.
Switching between preview frames of the same layout preserves each frame's selection.
Switching frames resets the settings sidebar width, with a wider default for rich-text editing.
Sidebar widths are not retained per frame.
Editing, undoing, saving, or discarding layout content affects every frame referencing that layout.
Closing a frame keeps the draft and history while another frame references the layout.
Closing its last frame uses the normal unsaved-change confirmation.
Closing the active frame clears the layout and element selection, leaves the remaining frames inactive, and returns the sidebar to the layout overview.

## Existing configurations

Configurations saved before independent preview frames have no frame `id` and contain at most one frame per layout.
The Administration normalizes these entries using `layoutId` as the initial frame ID and writes explicit frame IDs on subsequent saves.
The write validator accepts this existing shape and checks uniqueness using `id`, falling back to `layoutId` when `id` is absent.
New frames receive independent IDs.
Frames without a language selection use the system language when it is available in the preview sales channel, otherwise that channel's default language.
Restoring a workspace replaces deleted, inactive, or unassigned language references with the channel's default language and reports the missing reference.

Restoring a workspace loads each distinct layout once and reconnects its frames to the shared draft.
Frames referencing a deleted layout are skipped, and existing preview-reference recovery applies separately to each frame.
Frames of a newly created layout are included in persisted workspace configuration only after the layout has been saved.
