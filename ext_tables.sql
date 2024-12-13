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
    source_lang       varchar(10)      default ''  not null,
    target_lang       varchar(10)      default ''  not null
);

CREATE TABLE pages
(
    glossary_information                    int(11) unsigned default '0' not null
);
