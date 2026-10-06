<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Domain\Repository;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

// @todo Consider to rename/move this as service class.
final class GlossaryEntryRepository
{
    /**
     * @deprecated
     */
    public function hasEntriesForGlossary(int $parentId): bool
    {
        $entries = $this->findEntriesByGlossary($parentId);
        return count($entries) > 0;
    }

    /**
     * @return array<int, array<string, mixed>>
     * @deprecated
     */
    public function findEntriesByGlossary(int $parentId): array
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_deepltranslate_glossaryentry');

        $result = $connection->select(
            ['*'],
            'tx_deepltranslate_glossaryentry',
            [
                'glossary' => $parentId,
            ]
        );

        return $result->fetchAllAssociative() ?: [];
    }

    /**
     * @return array<string, mixed>
     */
    public function findEntryByUid(int $uid): array
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_deepltranslate_glossaryentry');

        $result = $connection->select(
            ['*'],
            'tx_deepltranslate_glossaryentry',
            [
                'uid' => $uid,
            ]
        );

        // @todo Should we not better returning null instead of an empty array if nor recourd could be retrieved ?
        return $result->fetchAssociative() ?: [];
    }

    /**
     * Returns the folder an entry belongs to, also for an entry which is hidden or already
     * deleted, or null for an unknown uid.
     */
    public function findPageOfEntry(int $uid): ?int
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_deepltranslate_glossaryentry');
        // A hidden or deleted entry still tells which folder changed its terms.
        $queryBuilder->getRestrictions()->removeAll();
        $pid = $queryBuilder
            ->select('pid')
            ->from('tx_deepltranslate_glossaryentry')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchOne();

        return $pid === false ? null : (int)$pid;
    }
}
