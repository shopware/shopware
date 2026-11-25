# Experience Studio workspaces

A `content_workspace` stores the canvas setup independently of layout content.
Its `configuration` contains no layout content, unsaved drafts, or undo history.

## Configuration

| Key | Stored value |
| --- | --- |
| `frames` | List of preview frame configurations; may be empty. |
| `previewSalesChannelId` | Preview sales channel UUID or `null`. |
| `canvas` | Camera object containing the offsets `panX` and `panY`, and scale `zoom`. |

## Frame entries

Each entry in `configuration.frames` contains:

| Key | Stored value |
| --- | --- |
| `id` | Required frame UUID, unique within the workspace. |
| `layoutId` | Referenced `content_layout` UUID; several frames may reference the same layout. |
| `x`, `y` | Frame position on the canvas. |
| `width`, `height` | Frame dimensions in pixels. |
| `viewport` | `mobile`, `tablet-landscape`, `desktop`, or `custom`. |
| `previewEntityId` | Preview entity UUID or `null`. |
| `languageId` | Optional language UUID. |
