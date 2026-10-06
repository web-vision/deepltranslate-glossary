<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Service;

use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use WebVision\Deepltranslate\Glossary\Event\ModifyGlossaryNameEvent;

/**
 * Names the DeepL glossary of a glossary folder, see {@see ModifyGlossaryNameEvent} to change it.
 *
 * Used when the glossary record of a folder is created or migrated. An existing record keeps its
 * name, so renaming the folder later does not rename its glossary.
 */
#[Autoconfigure(public: true)]
final readonly class GlossaryNameService
{
    /**
     * The longest glossary name DeepL accepts, in UTF-8 bytes.
     */
    public const MAX_NAME_BYTES = 1024;

    public function __construct(
        private ConnectionPool $connectionPool,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function getGlossaryName(int $pageId): string
    {
        $folderTitle = $this->getFolderTitle($pageId);
        $defaultName = sprintf('%s [%d]', $folderTitle !== '' ? $folderTitle : 'Glossary', $pageId);

        $event = new ModifyGlossaryNameEvent($pageId, $folderTitle, $defaultName);
        $this->eventDispatcher->dispatch($event);
        $glossaryName = trim($event->glossaryName);

        return $this->cutToByteLimit($glossaryName !== '' ? $glossaryName : $defaultName);
    }

    /**
     * Returns the name a glossary is published under from the name stored in its record: a new
     * one when the stored name is blank, as DeepL refuses a glossary without a name, and the
     * stored one cut to the length DeepL accepts otherwise.
     */
    public function fitStoredName(string $storedName, int $pageId): string
    {
        $storedName = trim($storedName);

        return $storedName !== '' ? $this->cutToByteLimit($storedName) : $this->getGlossaryName($pageId);
    }

    /**
     * Unlike a term, a name may be cut: it only labels the glossary. mb_strcut() never splits a
     * multibyte character, it cuts in front of it.
     */
    private function cutToByteLimit(string $glossaryName): string
    {
        if (strlen($glossaryName) <= self::MAX_NAME_BYTES) {
            return $glossaryName;
        }

        return rtrim(mb_strcut($glossaryName, 0, self::MAX_NAME_BYTES, 'UTF-8'));
    }

    private function getFolderTitle(int $pageId): string
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        // A hidden folder still holds a glossary, so only deleted pages are left out.
        $queryBuilder->getRestrictions()->removeAll()->add(new DeletedRestriction());
        $title = $queryBuilder
            ->select('title')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($pageId, Connection::PARAM_INT)
                )
            )
            ->executeQuery()
            ->fetchOne();

        return is_string($title) ? trim($title) : '';
    }
}
