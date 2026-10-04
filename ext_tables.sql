-- Nullable in released versions. The TCA would derive it as NOT NULL, which fails the database
-- compare of an instance holding a NULL value, and a nullable TCA field shows a NULL checkbox.
CREATE TABLE tx_deepltranslate_glossaryentry
(
    term varchar(1024) default ''
);

-- The language pair moved to `tx_deepltranslate_glossarydictionary` with glossary API v3.
-- Both columns have no TCA and are kept until the upgrade wizard has migrated existing
-- installations. They are removed with the next major version.
-- `glossary_ready` and `glossary_id` were nullable in released versions, which their TCA
-- cannot express, see `tx_deepltranslate_glossaryentry.term`.
CREATE TABLE tx_deepltranslate_glossary
(
    glossary_ready int(2) unsigned default '0',
    glossary_id    varchar(60)     default '',
    source_lang    varchar(10)     default '' not null,
    target_lang    varchar(10)     default '' not null
);

-- Every column is derived from the TCA. Translating resolves a glossary by language pair
-- through its dictionaries, joined on the glossary they belong to.
CREATE TABLE tx_deepltranslate_glossarydictionary
(
    KEY glossary (glossary),
    KEY language_pair (source_lang, target_lang)
);
