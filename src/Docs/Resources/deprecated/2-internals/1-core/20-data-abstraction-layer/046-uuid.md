[titleEn]: <>(UUID)
[hash]: <>(article:dal_uuid)

The data abstraction layer uses, contrary to many legacy data access implementations, [**universally unique identifiers** (short *UUID*)](https://en.wikipedia.org/wiki/Universally_unique_identifier) as its primary key format. A UUID is a 128-bit wide number represented as a hex value and stored binary in the database. A typical UUID in Shopware 6 will look like this:

```
4885d161e1144fcdaf78d039b8b73f80
```

A random string containing the numbers `0` to `9` and the characters `a` to `f` with a length of `32`.

This decision was not taken lightheartedly but was a logical conclusion following the API-First design goal. Shopware 6 and by token its storage engine do **not dictate** the primary key value of an entity but only employ requirements on the format. The classic approach to just use `AUTO_INCREMENT` columns is not sustainable for distributed, highly available and concurrent systems, because primary key creation is entirely wrapped inside the storage engine. UUIDs on the other hand are only created as a fallback on the server and can be supplied by any client. There is only a shared methodology to create new primary keys, but no central registry.

## Usage

UUIDs in Shopware 6 have two distinct representations: a hexadecimal representation as seen in the example above and a non printable and not human readable binary representation used by the storage engine. The validation, conversion and creation can be done through a single static interface found in `\Shopware\Core\Framework\Uuid\Uuid`. The following example will echo **`Equal`**:

```php
<?php

use \Shopware\Core\Framework\Uuid\Uuid;

$newHex = Uuid::randomHex();
$bytes = Uuid::fromHexToBytes($newHex);
$hex = Uuid::fromBytesToHex($bytes);

if($newHex === $hex) {
    echo "Equal";
}
```

Using UUIDs starts at the schema level. Primary as well as foreign key columns are of the type `BINARY(16)` and use the field type `\Shopware\Core\Framework\DataAbstractionLayer\Field\IdField` or  `\Shopware\Core\Framework\DataAbstractionLayer\Field\FkField` respectively in the entity definition. How to set up entities is explained in much greater details in the [following articles](./__categoryInfo.md) or the [howto section](./../../../4-how-to/__categoryInfo.md).

## Debugging

Debugging the Shopware 6 Database and API can be a little challenging due to the nature of UUIDs. In contrast to autoincrement values they are not **predictable** and due to the binary storage format not as easily **inspectable**. Luckily there are easy ways to fix this.

> **WARNING:** The following should not be used in production environments!

### Predictable UUIDs

Working only with random data - although useful as explained above - can be challenging. Although random distribution of values is the key to making UUIDs work, Shopware 6 actually does not enforce the randomness. Any string with a length of 32 and the reduced character set is a valid UUID and can be stored. The only limitation here is that a UUID can not be used twice for the same entity - just like autoincrement values. So valid debugging UUIDs may look like this: 

```
12345678901234567890123456789012
00000000000000000000000000000120
00000000000000000000000000000121
00000000000000000000000000000122
00000000000000000000000000000123
...
00000000000000000000000000010124
```

From here on out one can even write a simple generator that uses a counter to generate new UUIDs:

```php
<?php

function debugUuid(): string {
    static $increment = 0;
    return substr(($increment++) . 'fffffffffffffffffffffffffffffff', 0, 32);
}
```

Notice that this will effectively create an autoincrement value just padded to ensure the length.

### Inspectable database

On the database level all UUIDs are stored in their binary format. This sports a problem for a few of the tooling options out there. But also here we provide you with the choice to reconcile this with your tried and trusted debugging skills. The Shopware development template is equipped with a command to create views of all tables and cast the binary data into their hex representation. 

```bash
> bin/console database:generate-debug-views
```

Will create a mysql `VIEW` for every table in the database that already selects binary fields as hex values. These views follow the naming schema of `debug_{TABLE_NAME}`. This will work with any tool out there.

A tip from our development team is to use [Adminer](https://www.adminer.org/) since it can also display binary data in their hex representation automatically.  

