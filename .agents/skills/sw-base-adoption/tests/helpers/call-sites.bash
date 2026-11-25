# shellcheck shell=bash
# The class $1 under src/Core/Foo/ before and after the base migrates it from OldThing::make to NewHelper::create.
# Needs put.

call_site() {
    put "src/Core/Foo/$1.php" "<?php
class $1
{
    public const LEGACY = 1;
    public function run(\$value)
    {
        StillUsed::keep();
        return OldThing::make(\$value);
    }
}"
}

migrated_call_site() {
    put "src/Core/Foo/$1.php" "<?php
class $1
{
    public function run(\$value)
    {
        return NewHelper::create(\$value);
    }
}"
}
