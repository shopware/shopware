# Experience Studio workspaces

A `content_workspace` stores the canvas setup independently of layout content.
Several preview frames can reference the same `content_layout`.
The Administration keeps one working draft, saved baseline, and undo history for each layout, shared by all its frames.

## Frame identity and preview state

Each entry in `configuration.frames` has its own UUID `id` and a `layoutId` referencing the shared layout.
Frame IDs are unique within a workspace; layout IDs can repeat.
The frame also stores its `x`, `y`, `width`, `height`, `viewport`, and `previewEntityId`.
The workspace stores `previewSalesChannelId` and the canvas camera (`panX`, `panY`, and `zoom`).

Frame configuration contains no layout content or unsaved editor history.
Changing a viewport or preview entity changes only that frame.
Selecting an already open layout in the layout picker opens another preview of its current working draft.
Switching to a different layout clears the outgoing and incoming preview selections and closes the settings panel.
Switching between preview frames of the same layout preserves each frame's selection.
Editing, undoing, saving, or discarding layout content affects every frame referencing that layout.
Closing a frame keeps the draft and history while another frame references the layout.
Closing its last frame uses the normal unsaved-change confirmation.
Closing the active frame clears the layout and element selection, leaves the remaining frames inactive, and returns the sidebar to the layout overview.

## Existing configurations

Configurations saved before independent preview frames have no frame `id` and contain at most one frame per layout.
The Administration normalizes these entries using `layoutId` as the initial frame ID and writes explicit frame IDs on subsequent saves.
The write validator accepts this existing shape and checks uniqueness using `id`, falling back to `layoutId` when `id` is absent.
New frames receive independent IDs.

Restoring a workspace loads each distinct layout once and reconnects its frames to the shared draft.
Frames referencing a deleted layout are skipped, and existing preview-reference recovery applies separately to each frame.
Frames of a newly created layout are included in persisted workspace configuration only after the layout has been saved.
