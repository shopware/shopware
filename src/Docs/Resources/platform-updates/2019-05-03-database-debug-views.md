[titleEn]: <>(Database debugging)

```bash

>  bin/console database:generate-debug-views

```

Execute this command to create views in the database that replace all binary ids with hex values. All these views are named `debug_ORIGINAL_TABLE_NAME`.

#### Example

Executing `SELECT * FROM debug_tax` will result in:

```
+----------------------------------+----------+------+---------------+-------------------------+------------+
| id                               | tax_rate | name | custom_fields | created_at              | updated_at |
+----------------------------------+----------+------+---------------+-------------------------+------------+
| 12cb17bab0264ae4a518c0e053146a9c |    20.00 | 20%  | NULL          | 2019-05-03 12:04:10.000 | NULL       |
| 1eceb1547afe476ca39d49ca6a9c0047 |     5.00 | 5%   | NULL          | 2019-05-03 12:04:10.000 | NULL       |
| 6a456bf51f0b4a7c8655de754372be59 |     7.00 | 7%   | NULL          | 2019-05-03 12:04:10.000 | NULL       |
| 9015c672349c43f88c1143f6e382f52d |    19.00 | 19%  | NULL          | 2019-05-03 12:04:10.000 | NULL       |
| c081e2c696d04426840073a925634914 |     1.00 | 1%   | NULL          | 2019-05-03 12:04:10.000 | NULL       |
+----------------------------------+----------+------+---------------+-------------------------+------------+
``` 
