CREATE TABLE "s_categories" (
  "id" INTEGER PRIMARY KEY,
  "parent" int(11)  DEFAULT NULL,
  "path" varchar(255) DEFAULT NULL,
  "description" varchar(255) DEFAULT NULL
);

CREATE TABLE "s_articles_categories" (
  "id" INTEGER PRIMARY KEY,
  "articleID" int(11)  NOT NULL,
  "categoryID" int(11)  NOT NULL
);

CREATE TABLE "s_articles_categories_ro" (
  "id" INTEGER PRIMARY KEY,
  "articleID" int(11)  NOT NULL,
  "categoryID" int(11)  NOT NULL,
  "parentCategoryID" int(11)  NOT NULL
);
