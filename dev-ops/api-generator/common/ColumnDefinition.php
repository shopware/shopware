<?php

class ColumnDefinition
{
    public $table;
    public $name;
    public $type;
    public $required = false;

    public $default;
    public $hasDefault = false;
    public $propertyName;

    public $isPrimaryKey = false;

    public $isForeignKey = false;
    public $foreignKeyTable;
    public $foreignKeyColumn;

    public $translationTable = null;
    public $isTranslationField = false;
    public $allowNull;

    public $propertyNamePlural;
    public $allowHtml = false;
}
