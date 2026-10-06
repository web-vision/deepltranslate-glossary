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
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use WebVision\Deepltranslate\Core\Domain\Dto\CurrentPage;
use WebVision\Deepltranslate\Glossary\Domain\Dto\Glossary;
use WebVision\Deepltranslate\Glossary\Domain\Dto\GlossaryLanguageSelection;
use WebVision\Deepltranslate\Glossary\Domain\Dto\GlossarySyncInformation;
use WebVision\Deepltranslate\Glossary\Service\DeeplGlossaryService;
use WebVision\Deepltranslate\Glossary\Service\GlossaryLanguageResolver;
use WebVision\Deepltranslate\Glossary\Service\GlossaryNameService;
use WebVision\Deepltranslate\Glossary\Service\GlossaryTermSanitizer;
use WebVision\Deepltranslate\Glossary\Service\MultilingualGlossaryService;
use WebVision\Deepltranslate\Glossary\Upgrade\MigrateToMultilingualGlossaryWizard;

// @todo Consider to rename/move this as service class.
// @todo Split the collection of the terms of a folder (getDictionaryDataForSync() and its helpers)
//       into a service of its own, which also takes most constructor arguments with it.
#[Autoconfigure(public: true)]
final class GlossaryRepository
{
    public function __construct(
        private readonly Context $context,
        private readonly GlossaryTermSanitizer $termSanitizer,
        private readonly LoggerInterface $logger,
        private readonly ConnectionPool $connectionPool,
        private readonly SiteFinder $siteFinder,
        private readonly TranslationConfigurationProvider $translationConfigurationProvider,
        private readonly GlossaryNameService $glossaryNameService,
        private readonly GlossaryLanguageResolver $glossaryLanguageResolver,
    ) {
    }

    /**
     * @return Glossary[]
     *
     * @throws DBALException
     * @throws Exception
     * @throws SiteNotFoundException
     * @throws \Doctrine\DBAL\Exception
     *
     * @deprecated since 6.1, will be removed in 7.0. Creates a glossary record per language pair
     *             for the glossary API v2. Use {@see MultilingualGlossaryService::syncGlossary()}.
     */
    public function getGlossaryInformationForSync(int $pageId): array
    {
        trigger_error(
            'GlossaryRepository::getGlossaryInformationForSync() is deprecated since 6.1 and will be removed in 7.0.'
            . ' Use MultilingualGlossaryService::syncGlossary() instead.',
            E_USER_DEPRECATED
        );
        $page = BackendUtility::getRecord(
            'pages',
            $pageId
        );
        if ($page === null) {
            return [];
        }
        /** @var array{uid: int, title: string} $page */
        // Not injected on purpose: the deprecated service depends on this repository through
        // MultilingualGlossaryService, so injecting it would be circular.
        $availableLanguagePairs = GeneralUtility::makeInstance(DeeplGlossaryService::class)
            ->getPossibleGlossaryLanguageConfig();

        $glossaries = [];
        foreach ($this->getDictionaryDataForSync($pageId, $availableLanguagePairs)->dictionaries as $dictionary) {
            $glossaryInformation = $this->getGlossaryBySourceAndTargetForSync($dictionary['sourceLanguage'], $dictionary['targetLanguage'], $page);
            $glossaryInformation->sourceLanguage = $dictionary['sourceLanguage'];
            $glossaryInformation->targetLanguage = $dictionary['targetLanguage'];
            $glossaryInformation->entries = [];
            foreach ($dictionary['entries'] as $source => $target) {
                $glossaryInformation->entries[] = [
                    'source' => (string)$source,
                    'target' => $target,
                ];
            }
            $glossaries[] = $glossaryInformation;
        }

        return $glossaries;
    }

    /**
     * Collects the term pairs of a glossary folder grouped by language pair, without touching
     * any record. Used by the glossary API v3 synchronisation, which needs the dictionaries of
     * a folder before it knows whether a glossary has to be created at all.
     *
     * DeepL glossaries only know base language codes, so site languages like `en_GB` and
     * `en_US` compete for one code. {@see GlossaryLanguageResolver} decides which of them
     * provides the terms, the collisions it reports come with the dictionaries.
     *
     * @param array<string, array<array-key, string>> $languagePairs target languages by source language
     *
     * @throws DBALException
     * @throws Exception
     * @throws SiteNotFoundException
     */
    public function getDictionaryDataForSync(int $pageId, array $languagePairs): GlossarySyncInformation
    {
        $entries = $this->getOriginalEntries($pageId);
        if ($entries === []) {
            return new GlossarySyncInformation([], []);
        }
        $site = $this->siteFinder->getSiteByPageId($pageId);
        $siteLanguages = $site->getAllLanguages();
        $entriesByLanguageId = [0 => $entries];
        foreach ($this->getAvailableLocalizations($pageId) as $localizationLanguageId) {
            // A translation in a language removed from the site configuration has no language code.
            if (!isset($siteLanguages[$localizationLanguageId])) {
                continue;
            }
            $entriesByLanguageId[$localizationLanguageId] = $this->getLocalizedEntries($pageId, $localizationLanguageId);
        }
        $languageSelection = $this->glossaryLanguageResolver->resolve($site, array_map('count', $entriesByLanguageId));
        $localizationArray = $this->collectTermsByLanguageCode($entriesByLanguageId, $languageSelection);
        $sourceLangIsoCode = $site->getDefaultLanguage()->getLocale()->getLanguageCode();

        $dictionaries = [];
        foreach ($languagePairs as $sourceLang => $availableTargets) {
            foreach ($availableTargets as $targetLang) {
                if ($targetLang === $sourceLangIsoCode) {
                    continue;
                }
                $entries = $this->buildEntriesForPair($localizationArray, (string)$sourceLang, $targetLang);
                if ($entries === []) {
                    continue;
                }
                $dictionaries[] = [
                    'sourceLanguage' => (string)$sourceLang,
                    'targetLanguage' => $targetLang,
                    'entries' => $entries,
                ];
            }
        }

        return new GlossarySyncInformation($dictionaries, $languageSelection->collisions);
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
        $record = $this->connectionPool
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
            // A record written by a released version or by hand may come without a name, which
            // DeepL refuses. The name DeepL reports back is stored with the synchronisation.
            $record['glossary_name'] = $this->glossaryNameService->fitStoredName($record['glossary_name'], $pageId);
            return $record;
        }

        $connection = $this->connectionPool
            ->getConnectionForTable('tx_deepltranslate_glossary');
        // The name is chosen once, when the record is created. Later synchronisations keep it, so
        // renaming the folder or adding a listener of ModifyGlossaryNameEvent afterwards does not
        // rename an existing glossary.
        $glossaryName = $this->glossaryNameService->getGlossaryName($pageId);
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
        $this->connectionPool
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
                $this->replaceDictionaryRecords($connection, $information, $uid, $pageId);
            });
    }

    /**
     * Detaches the folder from its remote glossary, used when nothing is left to synchronise
     * and when DeepL no longer knows the glossary.
     *
     * @throws \Doctrine\DBAL\Exception
     */
    public function resetGlossaryRecord(int $uid): void
    {
        // A translation in between must not find dictionaries of a detached glossary.
        $this->connectionPool
            ->getConnectionForTable('tx_deepltranslate_glossary')
            ->transactional(function (Connection $connection) use ($uid): void {
                $connection->update(
                    'tx_deepltranslate_glossary',
                    [
                        'glossary_id' => '',
                        'glossary_lastsync' => 0,
                        'glossary_ready' => 0,
                    ],
                    ['uid' => $uid]
                );
                $this->deleteDictionaryRecords($connection, $uid);
            });
    }

    /**
     * Language codes are stored lowercase, the way {@see self::getGlossaryBySourceAndTarget()}
     * looks them up, whatever case DeepL answers with.
     *
     * @throws \Doctrine\DBAL\Exception
     */
    private function replaceDictionaryRecords(Connection $connection, MultilingualGlossaryInfo $information, int $uid, int $pageId): void
    {
        $this->deleteDictionaryRecords($connection, $uid);
        foreach ($information->dictionaries as $dictionary) {
            $connection->insert(
                'tx_deepltranslate_glossarydictionary',
                [
                    'pid' => $pageId,
                    'glossary' => $uid,
                    'source_lang' => strtolower($dictionary->sourceLang),
                    'target_lang' => strtolower($dictionary->targetLang),
                    'entry_count' => $dictionary->entryCount,
                    'in_sync' => 1,
                ]
            );
        }
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    private function deleteDictionaryRecords(Connection $connection, int $uid): void
    {
        $connection->delete('tx_deepltranslate_glossarydictionary', ['glossary' => $uid]);
    }

    /**
     * The cleaned terms of the site language selected for each glossary language code. Terms are
     * never mixed between site languages sharing a code.
     *
     * @param array<int, array<int|string, mixed>> $entriesByLanguageId
     * @return array<string, array<int, array{uid: int, term: string}>>
     */
    private function collectTermsByLanguageCode(array $entriesByLanguageId, GlossaryLanguageSelection $languageSelection): array
    {
        $localizationArray = [];
        foreach ($languageSelection->languageIdsByCode as $languageCode => $languageId) {
            if (isset($entriesByLanguageId[$languageId])) {
                $localizationArray[(string)$languageCode] = $this->normalizeTerms($entriesByLanguageId[$languageId]);
            }
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
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');

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
     * Tells whether a page belongs to a site, whose languages a glossary folder is synchronised in.
     */
    public function belongsToSite(int $pageId): bool
    {
        return $this->findRootPageIdOrNull($pageId) !== null;
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
        $db = $this->connectionPool
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

    /**
     * @deprecated since 6.1, will be removed in 7.0. Stores the state of a glossary of the
     *             glossary API v2. Use {@see MultilingualGlossaryService::syncGlossary()}.
     */
    public function updateLocalGlossary(GlossaryInfo $information, int $uid): void
    {
        trigger_error(
            'GlossaryRepository::updateLocalGlossary() is deprecated since 6.1 and will be removed in 7.0.'
            . ' Use MultilingualGlossaryService::syncGlossary() instead.',
            E_USER_DEPRECATED
        );
        $insertParams = [
            'glossary_id' => $information->glossaryId,
            'glossary_ready' => $information->ready ? 1 : 0,
            'glossary_lastsync' => $information->creationTime->getTimestamp(),
        ];

        $db = $this->connectionPool
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
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');

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

        $queryBuilder = $this->connectionPool
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
     *
     * @deprecated since 6.1, will be removed in 7.0. Creates a glossary record per language pair
     *             for the glossary API v2. Use {@see MultilingualGlossaryService::syncGlossary()}.
     */
    public function getGlossaryBySourceAndTargetForSync(
        string $sourceLanguage,
        string $targetLanguage,
        array $page
    ): Glossary {
        trigger_error(
            'GlossaryRepository::getGlossaryBySourceAndTargetForSync() is deprecated since 6.1 and will be removed in 7.0.'
            . ' Use MultilingualGlossaryService::syncGlossary() instead.',
            E_USER_DEPRECATED
        );
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
            $db = $this->connectionPool
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
        $db = $this->connectionPool
            ->getConnectionForTable('tx_deepltranslate_glossary');

        // The dictionaries describe the state of a glossary which is about to be dropped, so
        // they would otherwise survive as records of a glossary that no longer exists.
        $affected = $db->select(['uid'], 'tx_deepltranslate_glossary', ['glossary_id' => $glossaryId])
            ->fetchAllAssociative();
        foreach ($affected as $glossary) {
            $this->deleteDictionaryRecords($db, (int)$glossary['uid']);
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
        $db = $this->connectionPool
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
     * Tells whether a folder still holds a glossary record of the DeepL glossary API v2, which
     * stored one record per language pair.
     *
     * @todo Remove together with {@see MigrateToMultilingualGlossaryWizard}.
     *
     * @throws DBALException
     */
    public function hasGlossaryRecordOfApiV2(int $pageId): bool
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_deepltranslate_glossary');

        return $queryBuilder
            ->count('uid')
            ->from('tx_deepltranslate_glossary')
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pageId, Connection::PARAM_INT)),
                $queryBuilder->expr()->neq('source_lang', $queryBuilder->createNamedParameter('', Connection::PARAM_STR))
            )
            ->executeQuery()
            ->fetchOne() > 0;
    }

    /**
     * Tells whether a glossary folder holds any term in its default language, usable or not.
     *
     * @throws DBALException
     * @throws Exception
     */
    public function hasTerms(int $pageId): bool
    {
        return $this->getOriginalEntries($pageId) !== [];
    }

    /**
     * @return array<int, array{uid: int, term: string}>|array<empty>
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     * @throws DBALException
     */
    private function getOriginalEntries(int $pageId): array
    {
        $db = $this->connectionPool
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
        $db = $this->connectionPool
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
        $translations = $this->translationConfigurationProvider
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
        $db = $this->connectionPool
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
        $db = $this->connectionPool
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
        $site = $this->siteFinder->getSiteByPageId($pageId);
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
        $queryBuilder = $this->connectionPool
            ->getQueryBuilderForTable('tx_deepltranslate_glossarydictionary');
        $queryBuilder->update('tx_deepltranslate_glossarydictionary')
            ->set('in_sync', 0)
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pageId, Connection::PARAM_INT))
            )->executeStatement();
    }
}
