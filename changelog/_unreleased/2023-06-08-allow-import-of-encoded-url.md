---
title: Allow uploading of url encoded media
issue: NEXT-28428
author: Michael Köck
author_email: mkoeck@elektroshopkoeck.com
author_github: mkoeck
---
# Core
*  Changed deserialize method of src/Core/Content/ImportExport/DataAbstractionLayer/Serializer/Entity/MediaSerializer.php to urldecode the filename before saving it to the destination key of the cacheMediaFiles
___
# Upgrade Information
## MediaSerializer
### deserialize
The deserialize method of src/Core/Content/ImportExport/DataAbstractionLayer/Serializer/Entity/MediaSerializer.php was changed such that the filename will be urldecoded before saving it to the cacheMediaFiles.
Previously, encoded url raised an error during validateFileNameDoesNotContainForbiddenCharacter when importing them, because they contained % signs. 
On the other hand, not encoding urls raised an "Invalid media url" exception.

This change allows importing medias with filenames that contain special characters by supplying an encoded url in the import file.
