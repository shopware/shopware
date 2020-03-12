[titleEn]: <>(Media/File management)
[hash]: <>(article:internals_core_erd_content_media)

[Back to modules](./../10-modules.md)

Central file management of the system. The media component provides a rich set of services to analyze, modify and store rich media content. Thumbnails, videos and the like will be managed and stored by this component.

![Media/File management](./dist/erd-shopware-core-content-media.png)


### Table `media`

Root table for all media files managed by the system. Contains meta information, seo friendly url's and display friendly internationalized custom input. *Attention: A media item may actually not have a file when it was just recently created*.


### Table `media_default_folder`

All files related to one entity will be related and automatically assigned to this folder.


### Table `media_thumbnail`

A list of generated thumbnails related to a media item of an image type.


### Table `media_folder`

Folders represent a tree like structure just like a directory tree in any other file manager. They are related to a set of configuration options.


### Table `media_thumbnail_size`

Generated thumbnails to easily and reliably see whats generated.


### Table `media_folder_configuration`

Thumbnail generator related configuration of a folder.


[Back to modules](./../10-modules.md)
