<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Domain\Repository;

use DeepL\GlossaryInfo;
use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Exception as DBALException;
use TYPO3\CMS\Backend\Configuration\TranslationConfigurationProvider;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use WebVision\Deepltranslate\Core\Domain\Dto\CurrentPage;
use WebVision\Deepltranslate\Glossary\Domain\Dto\Glossary;
use WebVision\Deepltranslate\Glossary\Service\DeeplGlossaryService;

// @todo Consider to rename/move this as service class.
final class GlossaryRepository
{
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
        $glossaries = [];
        $localizationArray = [];

        $page = BackendUtility::getRecord(
            'pages',
            $pageId
        );

        if ($page === null) {
            return [];
        }
        /** @var array{uid: int, title: string} $page */
        $entries = $this->getOriginalEntries($pageId);
        if ($entries === []) {
            return [];
        }
        $localizationLanguageIds = $this->getAvailableLocalizations($pageId);
        $site = GeneralUtility::makeInstance(SiteFinder::class)
            ->getSiteByPageId($pageId);
        $sourceLangIsoCode = $site->getDefaultLanguage()->getLocale()->getLanguageCode();

        /** @var array<string, array<int, array{uid: int, term: string}>> $localizationArray */
        $localizationArray[$sourceLangIsoCode] = $entries;

        // fetch all language information available for building all glossaries
        foreach ($localizationLanguageIds as $localizationLanguageId) {
            $localizedEntries = $this->getLocalizedEntries($pageId, $localizationLanguageId);
            $targetLanguageIsoCode = $this->getTargetLanguageIsoCode($site, $localizationLanguageId);
            $localizationArray[$targetLanguageIsoCode] = $localizedEntries;
        }

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
        $db = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('pages');

        $identifiers = [
            'module' => 'glossary',
            'doktype' => 254,
            'sys_language_uid' => 0,
        ];

        return $db->select(
            ['uid'],
            'pages',
            $identifiers
        )->fetchAllAssociative() ?: [];
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
        return $this->getGlossary(
            $lowerSourceLang,
            $lowerTargetLang,
            $page->uid,
            true
        ) ?? $defaultGlossary;
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
            $lastInsertId = $db->lastInsertId('tx_deepltranslate_glossary');
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
     * @return array<int|string, array{uid: int, glossary_id: string}>
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     * @throws DBALException
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

        $result = $statement->executeQuery()->fetchAssociative();
        if ($result === false) {
            return [];
        }

        return $result;
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
            );
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
            );

        $result = $statement->executeQuery();

        $localizedEntries = [];
        while ($localizedEntry = $result->fetchAssociative()) {
            $localizedEntries[$localizedEntry['l10n_parent']] = $localizedEntry;
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
        int $pageUid,
        bool $recursive = false
    ): ?Glossary {
        $db = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_deepltranslate_glossary');

        $pidConstraint = null;
        if ($recursive === true) {
            $glossaryPages = $this->getGlossariesInRootByCurrentPage($pageUid);
            if (count($glossaryPages) > 0) {
                $pidConstraint = $db->expr()->in('pid', $glossaryPages);
            }
        } else {
            $pidConstraint = $db->expr()->eq('pid', $db->createNamedParameter($pageUid, Connection::PARAM_INT));
        }
        $where = $db->expr()->and(
            $db->expr()->eq('source_lang', $db->createNamedParameter($sourceLanguage)),
            $db->expr()->eq('target_lang', $db->createNamedParameter($targetLanguage)),
            $pidConstraint
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
     * @throws SiteNotFoundException
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
            ->where(
                $db->expr()->eq(
                    'doktype',
                    $db->createNamedParameter(
                        PageRepository::DOKTYPE_SYSFOLDER,
                        Connection::PARAM_INT
                    )
                ),
                $db->expr()->eq('module', $db->createNamedParameter('glossary'))
            )->executeQuery();

        $rows = $result->fetchAllAssociative();
        if (count($rows) === 0) {
            return [];
        }

        $rootPage = $this->findRootPageId($pageId);

        $ids = [];
        foreach ($rows as $row) {
            $glossaryRootPageID = $this->findRootPageId($row['uid']);
            if ($glossaryRootPageID !== $rootPage) {
                continue;
            }

            $ids[] = $row['uid'];
        }
        return $ids;
    }

    private function findRootPageId(int $pageId): int
    {
        $site = GeneralUtility::makeInstance(SiteFinder::class)->getSiteByPageId($pageId);
        return $site->getRootPageId();
    }

    public function setGlossaryNotSyncOnPage(int $pageId): void
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_deepltranslate_glossary');

        $queryBuilder->update('tx_deepltranslate_glossary')
            ->set('glossary_ready', 0)
            ->where(
                $queryBuilder->expr()->eq('pid', $pageId)
            )->executeStatement();
    }
}
