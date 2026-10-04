<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Domain\Repository;

use DeepL\GlossaryInfo;
use DeepL\MultilingualGlossaryInfo;
use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Exception as DBALException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Backend\Configuration\TranslationConfigurationProvider;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use WebVision\Deepltranslate\Core\Domain\Dto\CurrentPage;
use WebVision\Deepltranslate\Glossary\Domain\Dto\Glossary;
use WebVision\Deepltranslate\Glossary\Domain\Dto\GlossaryLanguageSelection;
use WebVision\Deepltranslate\Glossary\Domain\Dto\GlossarySyncInformation;
use WebVision\Deepltranslate\Glossary\Service\DeeplGlossaryService;
use WebVision\Deepltranslate\Glossary\Service\GlossaryLanguageResolver;
use WebVision\Deepltranslate\Glossary\Service\GlossaryTermSanitizer;

// @todo Consider to rename/move this as service class.
#[Autoconfigure(public: true)]
final class GlossaryRepository
{
    public function __construct(
        private readonly GlossaryLanguageResolver $glossaryLanguageResolver,
        private readonly Context $context,
        private readonly GlossaryTermSanitizer $termSanitizer,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return Glossary[]
     *
     * @throws DBALException
     * @throws Exception
     * @throws SiteNotFoundException
     * @throws \Doctrine\DBAL\Exception
     */
    public function getGlossaryInformationForSync(int $pageId): array
    {
        return $this->getGlossarySyncInformation($pageId)->glossaries;
    }

    /**
     * Glossaries of a glossary folder to send to DeepL, plus the collisions of site languages
     * sharing a glossary language code the site configuration does not resolve.
     *
     * @internal and not part of public API.
     *
     * @throws DBALException
     * @throws Exception
     * @throws SiteNotFoundException
     * @throws \Doctrine\DBAL\Exception
     */
    public function getGlossarySyncInformation(int $pageId): GlossarySyncInformation
    {
        $page = BackendUtility::getRecord(
            'pages',
            $pageId
        );

        if ($page === null) {
            return new GlossarySyncInformation([], []);
        }
        /** @var array{uid: int, title: string} $page */
        $entries = $this->getOriginalEntries($pageId);
        if ($entries === []) {
            return new GlossarySyncInformation([], []);
        }
        $site = GeneralUtility::makeInstance(SiteFinder::class)
            ->getSiteByPageId($pageId);
        $entriesByLanguageId = [0 => $entries];
        foreach ($this->getAvailableLocalizations($pageId) as $localizationLanguageId) {
            $entriesByLanguageId[$localizationLanguageId] = $this->getLocalizedEntries($pageId, $localizationLanguageId);
        }
        $languageSelection = $this->glossaryLanguageResolver->resolve($site, array_map('count', $entriesByLanguageId));

        return new GlossarySyncInformation(
            $this->buildGlossaries(
                $page,
                $this->mapEntriesToLanguageCodes($entriesByLanguageId, $languageSelection),
                $site->getDefaultLanguage()->getLocale()->getLanguageCode()
            ),
            $languageSelection->collisions
        );
    }

    /**
     * @param array<int, array<mixed>> $entriesByLanguageId
     * @return array<string, array<mixed>>
     */
    private function mapEntriesToLanguageCodes(
        array $entriesByLanguageId,
        GlossaryLanguageSelection $languageSelection
    ): array {
        $localizationArray = [];
        foreach ($languageSelection->languageIdsByCode as $languageCode => $languageId) {
            if (isset($entriesByLanguageId[$languageId])) {
                $localizationArray[$languageCode] = $entriesByLanguageId[$languageId];
            }
        }
        return $localizationArray;
    }

    /**
     * @param array{uid: int, title: string} $page
     * @param array<string, array<mixed>> $localizationArray
     * @return list<Glossary>
     *
     * @throws Exception
     * @throws SiteNotFoundException
     * @throws \Doctrine\DBAL\Exception
     */
    private function buildGlossaries(array $page, array $localizationArray, string $sourceLangIsoCode): array
    {
        $glossaries = [];
        $availableLanguagePairs = GeneralUtility::makeInstance(DeeplGlossaryService::class)
            ->getPossibleGlossaryLanguageConfig();
        foreach ($availableLanguagePairs as $sourceLang => $availableTargets) {
            // no entry to possible source in the current page
            if (!isset($localizationArray[$sourceLang])) {
                continue;
            }

            foreach ($availableTargets as $targetLang) {
                // target isn't configured in the current page
                if (!isset($localizationArray[$targetLang])) {
                    continue;
                }

                // target is site default, continue
                if ($targetLang === $sourceLangIsoCode) {
                    continue;
                }

                $glossaryInformation = $this->getGlossaryBySourceAndTargetForSync(
                    $sourceLang,
                    $targetLang,
                    $page
                );
                $glossaryInformation->sourceLanguage = $sourceLang;
                $glossaryInformation->targetLanguage = $targetLang;

                $entries = [];
                foreach ($localizationArray[$sourceLang] as $entryId => $sourceEntry) {
                    // no source target pair, next
                    if (!isset($localizationArray[$targetLang][$entryId])) {
                        continue;
                    }
                    $entries[] = [
                        'source' => $sourceEntry['term'],
                        'target' => $localizationArray[$targetLang][$entryId]['term'],
                    ];
                }
                // no pairs detected
                if (count($entries) == 0) {
                    continue;
                }
                // remove duplicates
                $sources = [];
                foreach ($entries as $position => $entry) {
                    if (in_array($entry['source'], $sources)) {
                        unset($entries[$position]);
                        continue;
                    }
                    $sources[] = $entry['source'];
                }

                // reset entries keys
                $glossaryInformation->entries = array_values($entries);
                $glossaries[] = $glossaryInformation;
            }
        }

        return $glossaries;
    }

    /**
     * Collects the term pairs of a glossary folder grouped by language pair, without touching
     * any record. Used by the glossary API v3 synchronisation, which needs the dictionaries of
     * a folder before it knows whether a glossary has to be created at all.
     *
     * @param array<string, array<array-key, string>> $languagePairs target languages by source language
     * @return array<int, array{sourceLanguage: string, targetLanguage: string, entries: array<string, string>}>
     *
     * @throws DBALException
     * @throws Exception
     * @throws SiteNotFoundException
     */
    public function getDictionaryDataForSync(int $pageId, array $languagePairs): array
    {
        $localizationArray = $this->collectTermsByLanguage($pageId);
        if ($localizationArray === []) {
            return [];
        }
        // The terms of the default language are collected first.
        $sourceLangIsoCode = (string)array_key_first($localizationArray);

        $dictionaries = [];
        foreach ($languagePairs as $sourceLang => $availableTargets) {
            foreach ($availableTargets as $targetLang) {
                if ($targetLang === $sourceLangIsoCode) {
                    continue;
                }
                $entries = $this->buildEntriesForPair($localizationArray, $sourceLang, $targetLang);
                if ($entries === []) {
                    continue;
                }
                $dictionaries[] = [
                    'sourceLanguage' => $sourceLang,
                    'targetLanguage' => $targetLang,
                    'entries' => $entries,
                ];
            }
        }

        return $dictionaries;
    }

    /**
     * Returns the single glossary record of a folder, or null when the folder has none.
     *
     * @return array{uid: int, glossary_id: string, glossary_name: string}|null
     *
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public function findGlossaryRecord(int $pageId): ?array
    {
        $record = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_deepltranslate_glossary')
            ->select(['uid', 'glossary_id', 'glossary_name'], 'tx_deepltranslate_glossary', ['pid' => $pageId], [], ['uid' => 'ASC'], 1)
            ->fetchAssociative();
        if ($record === false) {
            return null;
        }

        // Released versions left glossary_id nullable.
        return [
            'uid' => (int)$record['uid'],
            'glossary_id' => (string)$record['glossary_id'],
            'glossary_name' => (string)$record['glossary_name'],
        ];
    }

    /**
     * Returns the single glossary record of a folder, creating it when the folder has none yet.
     *
     * @return array{uid: int, glossary_id: string, glossary_name: string}
     *
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public function findOrCreateGlossaryRecord(int $pageId): array
    {
        $record = $this->findGlossaryRecord($pageId);
        if ($record !== null) {
            return $record;
        }

        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_deepltranslate_glossary');
        $page = BackendUtility::getRecord('pages', $pageId, 'uid,title');
        $glossaryName = sprintf('%s [%d]', $page['title'] ?? 'Glossary', $pageId);
        $connection->insert(
            'tx_deepltranslate_glossary',
            [
                'pid' => $pageId,
                'glossary_id' => '',
                'glossary_name' => $glossaryName,
                'glossary_lastsync' => 0,
                'glossary_ready' => 0,
            ]
        );

        return [
            'uid' => (int)$connection->lastInsertId(),
            'glossary_id' => '',
            'glossary_name' => $glossaryName,
        ];
    }

    /**
     * Mirrors the state DeepL reported back onto the glossary record and its dictionaries.
     *
     * @throws \Doctrine\DBAL\Exception
     */
    public function updateGlossaryRecord(MultilingualGlossaryInfo $information, int $uid, int $pageId): void
    {
        // A translation in between must not find the glossary without its dictionaries.
        GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_deepltranslate_glossary')
            ->transactional(function (Connection $connection) use ($information, $uid, $pageId): void {
                $connection->update(
                    'tx_deepltranslate_glossary',
                    [
                        'glossary_id' => $information->glossaryId,
                        'glossary_name' => $information->name,
                        // DeepL keeps the creation time of a glossary edited in place.
                        'glossary_lastsync' => $this->context->getPropertyFromAspect('date', 'timestamp'),
                        'glossary_ready' => 1,
                    ],
                    ['uid' => $uid]
                );
                $this->replaceDictionaryRecords($information, $uid, $pageId);
            });
    }

    /**
     * Detaches the folder from its remote glossary, used when nothing is left to synchronise.
     *
     * @throws \Doctrine\DBAL\Exception
     */
    public function resetGlossaryRecord(int $uid): void
    {
        GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_deepltranslate_glossary')
            ->update(
                'tx_deepltranslate_glossary',
                [
                    'glossary_id' => '',
                    'glossary_lastsync' => 0,
                    'glossary_ready' => 0,
                ],
                ['uid' => $uid]
            );

        $this->deleteDictionaryRecords($uid);
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    private function replaceDictionaryRecords(MultilingualGlossaryInfo $information, int $uid, int $pageId): void
    {
        $this->deleteDictionaryRecords($uid);
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_deepltranslate_glossarydictionary');
        foreach ($information->dictionaries as $dictionary) {
            $connection->insert(
                'tx_deepltranslate_glossarydictionary',
                [
                    'pid' => $pageId,
                    'glossary' => $uid,
                    'source_lang' => $dictionary->sourceLang,
                    'target_lang' => $dictionary->targetLang,
                    'entry_count' => $dictionary->entryCount,
                    'in_sync' => 1,
                ]
            );
        }
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    private function deleteDictionaryRecords(int $uid): void
    {
        GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_deepltranslate_glossarydictionary')
            ->delete('tx_deepltranslate_glossarydictionary', ['glossary' => $uid]);
    }

    /**
     * @return array<string, array<int, array{uid: int, term: string}>>
     *
     * @throws DBALException
     * @throws Exception
     * @throws SiteNotFoundException
     */
    private function collectTermsByLanguage(int $pageId): array
    {
        $entries = $this->getOriginalEntries($pageId);
        if ($entries === []) {
            return [];
        }
        $site = GeneralUtility::makeInstance(SiteFinder::class)->getSiteByPageId($pageId);
        $localizationArray = [
            $site->getDefaultLanguage()->getLocale()->getLanguageCode() => $this->normalizeTerms($entries),
        ];
        $siteLanguages = $site->getAllLanguages();
        foreach ($this->getAvailableLocalizations($pageId) as $localizationLanguageId) {
            // A translation in a language removed from the site configuration has no language code.
            if (!isset($siteLanguages[$localizationLanguageId])) {
                continue;
            }
            $localizationArray[$siteLanguages[$localizationLanguageId]->getLocale()->getLanguageCode()]
                = $this->normalizeTerms($this->getLocalizedEntries($pageId, $localizationLanguageId));
        }

        return $localizationArray;
    }

    /**
     * Tells whether a page is a visible folder set up as glossary, the only kind of page
     * synchronised to DeepL. A hidden folder is left out, as its glossary is not used either, and
     * so is a translation of a folder, as the terms of every language belong to the folder itself.
     */
    public function isGlossaryFolder(int $pageId): bool
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('pages');

        return $queryBuilder
            ->count('uid')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($pageId, Connection::PARAM_INT)),
                ...$this->getGlossaryFolderConstraints($queryBuilder)
            )
            ->executeQuery()
            ->fetchOne() > 0;
    }

    /**
     * The constraints of a glossary folder, shared by every query deciding which folders are
     * synchronised or used for translations, so the two cannot disagree.
     *
     * @return string[]
     */
    private function getGlossaryFolderConstraints(QueryBuilder $queryBuilder): array
    {
        return [
            $queryBuilder->expr()->eq('doktype', $queryBuilder->createNamedParameter(PageRepository::DOKTYPE_SYSFOLDER, Connection::PARAM_INT)),
            $queryBuilder->expr()->eq('module', $queryBuilder->createNamedParameter('glossary', Connection::PARAM_STR)),
            $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
        ];
    }

    /**
     * @param array<string, array<int, array{uid: int, term: string}>> $localizationArray
     * @return array<string, string>
     */
    private function buildEntriesForPair(array $localizationArray, string $sourceLang, string $targetLang): array
    {
        if (!isset($localizationArray[$sourceLang], $localizationArray[$targetLang])) {
            return [];
        }

        $entries = [];
        foreach ($localizationArray[$sourceLang] as $entryId => $sourceEntry) {
            $targetTerm = $localizationArray[$targetLang][$entryId]['term'] ?? '';
            if ($sourceEntry['term'] === '' || $targetTerm === '') {
                continue;
            }
            // DeepL rejects the whole dictionary for a single term above its byte limit, which the
            // backend refuses to store since DPL-230, but older or imported terms may still hold.
            if ($this->termSanitizer->exceedsByteLimit($sourceEntry['term'])
                || $this->termSanitizer->exceedsByteLimit($targetTerm)
            ) {
                $this->logger->warning(sprintf(
                    'Glossary term pair of record %d (%s => %s) exceeds the DeepL limit of %d UTF-8 bytes and is skipped.',
                    $sourceEntry['uid'],
                    $sourceLang,
                    $targetLang,
                    GlossaryTermSanitizer::MAX_TERM_BYTES
                ));
                continue;
            }
            // DeepL accepts a source term once per dictionary. The terms are cleaned and ordered
            // by uid already, so the oldest pair wins regardless of whitespace or database.
            if (isset($entries[$sourceEntry['term']])) {
                $this->logger->warning(sprintf(
                    'Glossary term "%s" (%s => %s) occurs more than once, record %d is skipped.',
                    $sourceEntry['term'],
                    $sourceLang,
                    $targetLang,
                    $sourceEntry['uid']
                ));
                continue;
            }
            $entries[$sourceEntry['term']] = $targetTerm;
        }

        return $entries;
    }

    /**
     * The rows come straight from the database, so both key and value types are widened. The
     * dictionary building relies on the term of a translation being addressable by the uid of
     * its default language record.
     *
     * @param array<int|string, mixed> $rows
     * @return array<int, array{uid: int, term: string}>
     */
    private function normalizeTerms(array $rows): array
    {
        $terms = [];
        foreach ($rows as $key => $row) {
            if (!is_array($row) || !isset($row['term'])) {
                continue;
            }
            $terms[(int)$key] = [
                'uid' => (int)($row['uid'] ?? 0),
                'term' => $this->termSanitizer->sanitize((string)$row['term']),
            ];
        }

        return $terms;
    }

    /**
     * @throws Exception
     */
    public function findByGlossaryId(string $glossaryId): ?Glossary
    {
        $db = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_deepltranslate_glossary');

        $result = $db
            ->select(
                ['*'],
                'tx_deepltranslate_glossary',
                [
                    'glossary_id' => $glossaryId,
                ],
                [],
                [],
                1
            )
            ->fetchAssociative();

        return $result ? Glossary::fromDatabase($result) : null;
    }

    public function updateLocalGlossary(GlossaryInfo $information, int $uid): void
    {
        $insertParams = [
            'glossary_id' => $information->glossaryId,
            'glossary_ready' => $information->ready ? 1 : 0,
            'glossary_lastsync' => $information->creationTime->getTimestamp(),
        ];

        $db = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_deepltranslate_glossary');

        $db->update(
            'tx_deepltranslate_glossary',
            $insertParams,
            [
                'uid' => $uid,
            ]
        );
    }

    /**
     * @return array<int|string, mixed>
     * @throws Exception
     */
    public function findAllGlossaries(): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('pages');

        return $queryBuilder
            ->select('uid')
            ->from('pages')
            ->where(...$this->getGlossaryFolderConstraints($queryBuilder))
            ->orderBy('uid')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @return Glossary
     *
     * @throws Exception
     * @throws SiteNotFoundException
     * @throws \Doctrine\DBAL\Exception
     */
    public function getGlossaryBySourceAndTarget(
        string $sourceLanguage,
        string $targetLanguage,
        ?CurrentPage $page
    ): Glossary {
        $defaultGlossary = Glossary::createDummy();
        if ($page === null) {
            return $defaultGlossary;
        }
        $lowerSourceLang = strtolower($sourceLanguage);
        $lowerTargetLang = strtolower($targetLanguage);
        if (strlen($lowerTargetLang) > 2) {
            $lowerTargetLang = substr($lowerTargetLang, 0, 2);
        }
        return $this->getGlossaryByDictionary(
            $lowerSourceLang,
            $lowerTargetLang,
            $page->uid
        ) ?? $defaultGlossary;
    }

    /**
     * Resolves the glossary of a folder covering the wanted language pair.
     *
     * A glossary of the API v3 is valid for every language pair one of its dictionaries covers,
     * so the pair is matched on the dictionaries and not on the glossary record.
     *
     * @throws Exception
     * @throws SiteNotFoundException
     * @throws \Doctrine\DBAL\Exception
     */
    private function getGlossaryByDictionary(
        string $sourceLanguage,
        string $targetLanguage,
        int $pageUid
    ): ?Glossary {
        // Only glossary module folders can be synchronised, so only their glossaries are current.
        $glossaryPages = $this->getGlossariesInRootByCurrentPage($pageUid);
        if ($glossaryPages === []) {
            return null;
        }

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_deepltranslate_glossary');
        $row = $queryBuilder
            ->select('g.uid', 'g.pid', 'g.glossary_id', 'g.glossary_name', 'g.glossary_lastsync', 'g.glossary_ready')
            ->from('tx_deepltranslate_glossary', 'g')
            ->innerJoin(
                'g',
                'tx_deepltranslate_glossarydictionary',
                'd',
                $queryBuilder->expr()->eq('d.glossary', $queryBuilder->quoteIdentifier('g.uid'))
            )
            ->where(
                $queryBuilder->expr()->eq('d.source_lang', $queryBuilder->createNamedParameter($sourceLanguage)),
                $queryBuilder->expr()->eq('d.target_lang', $queryBuilder->createNamedParameter($targetLanguage)),
                $queryBuilder->expr()->in('g.pid', $queryBuilder->createNamedParameter($glossaryPages, Connection::PARAM_INT_ARRAY)),
                // A glossary not ready to use must not hide a usable one of another folder.
                $queryBuilder->expr()->eq('g.glossary_ready', $queryBuilder->createNamedParameter(1, Connection::PARAM_INT)),
                $queryBuilder->expr()->neq('g.glossary_id', $queryBuilder->createNamedParameter(''))
            )
            ->orderBy('g.uid')
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        return $row === false ? null : Glossary::fromDatabase($row);
    }

    /**
     * @param array{uid: int, title: string} $page
     * @throws Exception
     * @throws SiteNotFoundException
     * @throws \Doctrine\DBAL\Exception
     */
    public function getGlossaryBySourceAndTargetForSync(
        string $sourceLanguage,
        string $targetLanguage,
        array $page
    ): Glossary {
        $lowerSourceLang = strtolower($sourceLanguage);
        $lowerTargetLang = strtolower($targetLanguage);
        if (strlen($lowerTargetLang) > 2) {
            $lowerTargetLang = substr($lowerTargetLang, 0, 2);
        }

        $result = $this->getGlossary($lowerSourceLang, $lowerTargetLang, (int)$page['uid']);

        if ($result === null) {
            $insert = [
                'glossary_name' => sprintf(
                    '%s: %s => %s',
                    $page['title'],
                    $sourceLanguage,
                    $targetLanguage
                ),
                'glossary_id' => '',
                'glossary_lastsync' => 0,
                'glossary_ready' => 0,
                'source_lang' => $lowerSourceLang,
                'target_lang' => $lowerTargetLang,
                'pid' => $page['uid'],
            ];
            $db = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getConnectionForTable('tx_deepltranslate_glossary');
            $db->insert('tx_deepltranslate_glossary', $insert);
            $lastInsertId = $db->lastInsertId();
            $insert['uid'] = $lastInsertId;
            unset($insert['pid']);
            return Glossary::fromDatabase($insert);
        }

        return $result;
    }

    public function removeGlossarySync(string $glossaryId): bool
    {
        $db = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_deepltranslate_glossary');

        // The dictionaries describe the state of a glossary which is about to be dropped, so
        // they would otherwise survive as records of a glossary that no longer exists.
        $affected = $db->select(['uid'], 'tx_deepltranslate_glossary', ['glossary_id' => $glossaryId])
            ->fetchAllAssociative();
        foreach ($affected as $glossary) {
            $this->deleteDictionaryRecords((int)$glossary['uid']);
        }

        $count = $db->update(
            'tx_deepltranslate_glossary',
            [
                'glossary_id' => '',
                'glossary_lastsync' => 0,
                'glossary_ready' => 0,
            ],
            [
                'glossary_id' => $glossaryId,
            ]
        );

        return $count >= 1;
    }

    /**
     * @return list<array{uid: int, glossary_id: string}>
     * @throws \Doctrine\DBAL\Exception
     */
    public function getGlossariesDeeplConnected(): array
    {
        $db = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_deepltranslate_glossary');
        $statement = $db
            ->select('uid', 'glossary_id')
            ->from('tx_deepltranslate_glossary')
            ->where(
                $db->expr()->neq('glossary_id', $db->createNamedParameter(''))
            );

        $glossaries = [];
        $result = $statement->executeQuery();
        while ($row = $result->fetchAssociative()) {
            $glossaries[] = [
                'uid' => (int)$row['uid'],
                'glossary_id' => (string)$row['glossary_id'],
            ];
        }

        return $glossaries;
    }

    /**
     * @return array<int, array{uid: int, term: string}>|array<empty>
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     * @throws DBALException
     */
    private function getOriginalEntries(int $pageId): array
    {
        $db = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_deepltranslate_glossaryentry');
        $statement = $db
            ->select('uid', 'term')
            ->from('tx_deepltranslate_glossaryentry')
            ->where(
                $db->expr()->eq(
                    'pid',
                    $db->createNamedParameter($pageId, Connection::PARAM_INT)
                ),
                $db->expr()->eq(
                    'sys_language_uid',
                    $db->createNamedParameter(0, Connection::PARAM_INT)
                )
            )
            ->orderBy('uid');
        $entries = [];
        foreach ($statement->executeQuery()->fetchAllAssociative() ?: [] as $entry) {
            $entries[$entry['uid']] = $entry;
        }
        return $entries;
    }

    /**
     * @return array<int, array{uid: int, term: string, l10n_parent: int}>|array<array<string, mixed>>
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     * @throws DBALException
     */
    private function getLocalizedEntries(int $pageId, int $languageId): array
    {
        $db = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_deepltranslate_glossaryentry');
        $statement = $db
            ->select('uid', 'term', 'l10n_parent')
            ->from('tx_deepltranslate_glossaryentry')
            ->where(
                $db->expr()->eq(
                    'pid',
                    $db->createNamedParameter($pageId, Connection::PARAM_INT)
                ),
                $db->expr()->eq(
                    'sys_language_uid',
                    $db->createNamedParameter($languageId, Connection::PARAM_INT)
                )
            )
            // Keyed by the default language record, so a translated source language keeps its order.
            ->orderBy('l10n_parent')
            ->addOrderBy('uid');

        $result = $statement->executeQuery();

        $localizedEntries = [];
        while ($localizedEntry = $result->fetchAssociative()) {
            $localizedEntries[$localizedEntry['l10n_parent']] ??= $localizedEntry;
        }
        return $localizedEntries;
    }

    /**
     * @return array<int, mixed>
     */
    private function getAvailableLocalizations(int $pageId): array
    {
        $translations = GeneralUtility::makeInstance(TranslationConfigurationProvider::class)
            ->translationInfo('pages', $pageId);

        // Error string given, if not matching. Return an empty array then
        if (!is_array($translations)) {
            return [];
        }
        $availableTranslations = [];
        foreach ($translations['translations'] as $translation) {
            $availableTranslations[] = (int)$translation['sys_language_uid'];
        }

        return $availableTranslations;
    }

    protected function getTargetLanguageIsoCode(Site $site, int $languageId): string
    {
        return $site->getLanguageById($languageId)->getLocale()->getLanguageCode();
    }

    /**
     * @throws SiteNotFoundException
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    private function getGlossary(
        string $sourceLanguage,
        string $targetLanguage,
        int $pageUid
    ): ?Glossary {
        $db = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_deepltranslate_glossary');

        $where = $db->expr()->and(
            $db->expr()->eq('source_lang', $db->createNamedParameter($sourceLanguage)),
            $db->expr()->eq('target_lang', $db->createNamedParameter($targetLanguage)),
            $db->expr()->eq('pid', $db->createNamedParameter($pageUid, Connection::PARAM_INT))
        );

        $statement = $db
            ->select(
                'uid',
                'glossary_id',
                'glossary_name',
                'glossary_lastsync',
                'glossary_ready',
            )
            ->from('tx_deepltranslate_glossary')
            ->where($where)
            ->setMaxResults(1);

        $result = $statement->executeQuery()->fetchAssociative();

        return $result ? Glossary::fromDatabase($result) : null;
    }

    /**
     * @return int[]
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    private function getGlossariesInRootByCurrentPage(int $pageId): array
    {
        $db = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('pages');

        $result = $db
            ->select('uid')
            ->from('pages')
            ->where(...$this->getGlossaryFolderConstraints($db))
            ->executeQuery();

        $rootPage = $this->findRootPageIdOrNull($pageId);
        $ids = [];
        // A glossary folder outside any site belongs to no site, so it is never one of the current site.
        while ($rootPage !== null && $row = $result->fetchAssociative()) {
            if ($this->findRootPageIdOrNull((int)$row['uid']) === $rootPage) {
                $ids[] = (int)$row['uid'];
            }
        }

        return $ids;
    }

    private function findRootPageId(int $pageId): int
    {
        $site = GeneralUtility::makeInstance(SiteFinder::class)->getSiteByPageId($pageId);
        return $site->getRootPageId();
    }

    private function findRootPageIdOrNull(int $pageId): ?int
    {
        try {
            return $this->findRootPageId($pageId);
        } catch (SiteNotFoundException) {
            return null;
        }
    }

    /**
     * Marks the dictionaries of a folder as no longer matching its terms.
     *
     * The glossary stays ready, so translations keep using its last synchronised state until the
     * folder is synchronised again.
     */
    public function setGlossaryNotSyncOnPage(int $pageId): void
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_deepltranslate_glossarydictionary');
        $queryBuilder->update('tx_deepltranslate_glossarydictionary')
            ->set('in_sync', 0)
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pageId, Connection::PARAM_INT))
            )->executeStatement();
    }
}
