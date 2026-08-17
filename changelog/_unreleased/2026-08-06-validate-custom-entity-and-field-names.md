---
title: Custom entity and field names are validated before the schema is built
issue: #304
---
# Core
* Entity and field names in `Resources/entities.xml` become table and column names in the generated schema. They are now validated when the app or plugin is installed or updated, and may only contain letters, digits, underscores, `$`, and non-ASCII bytes supported by MySQL/MariaDB identifiers. A manifest using whitespace or punctuation such as `-` is rejected with a clear error.
