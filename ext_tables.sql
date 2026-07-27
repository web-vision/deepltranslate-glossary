CREATE TABLE tx_deepltranslate_glossaryentry
(
    term varchar(255) default ''
);


create table tx_deepltranslate_glossary
(
    glossary_ready    int(2) unsigned  default '0',
    glossary_lastsync int(11) unsigned default '0' not null,
    glossary_id       varchar(60)      default '',
    glossary_name     varchar(255)     default ''  not null,
    -- The language pair moved to `tx_deepltranslate_glossarydictionary` with glossary API v3.
    -- Both columns are kept until the upgrade wizard has migrated existing installations and
    -- are removed with the next major version.
    source_lang       varchar(10)      default ''  not null,
    target_lang       varchar(10)      default ''  not null
);

-- Only the parent pointer needs an explicit definition, every other column of this table is
-- derived from its TCA.
create table tx_deepltranslate_glossarydictionary
(
    glossary int(11) unsigned default '0' not null
);

CREATE TABLE pages
(
    glossary_information                    int(11) unsigned default '0' not null
);
