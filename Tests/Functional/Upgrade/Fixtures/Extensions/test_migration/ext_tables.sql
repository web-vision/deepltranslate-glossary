create table tx_wvdeepltranslate_glossary
(
	uid               int unsigned auto_increment primary key,
	pid               int unsigned      default 0  not null,
	tstamp            int unsigned      default 0  not null,
	crdate            int unsigned      default 0  not null,
	deleted           smallint unsigned default 0  not null,
	glossary_ready    int unsigned      default 0  null,
	glossary_lastsync int unsigned      default 0  not null,
	glossary_id       varchar(60)       default '' null,
	glossary_name     varchar(255)      default '' not null,
	source_lang       varchar(10)       default '' not null,
	target_lang       varchar(10)       default '' not null
);

create table tx_wvdeepltranslate_glossaryentry
(
	uid              int unsigned auto_increment primary key,
	pid              int unsigned      default 0  not null,
	tstamp           int unsigned      default 0  not null,
	crdate           int unsigned      default 0  not null,
	deleted          smallint unsigned default 0  not null,
	hidden           smallint unsigned default 0  not null,
	sys_language_uid int               default 0  not null,
	l10n_parent      int unsigned      default 0  not null,
	l10n_source      int unsigned      default 0  not null,
	l10n_state       text                         null,
	l10n_diffsource  mediumblob                   null,
	term             varchar(255)      default '' null
);
