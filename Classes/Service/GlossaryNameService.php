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
     * The longest glossary name, in characters: the record stores up to 255 characters, and 255
     * characters are at most 1020 UTF-8 bytes, below the 1024 bytes DeepL accepts for a name.
     */
    public const MAX_NAME_LENGTH = 255;

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

        return $this->cutToMaximumLength($glossaryName !== '' ? $glossaryName : $defaultName);
    }

    /**
     * Returns the name a glossary is published under from the name stored in its record: a new
     * one when the stored name is blank, as DeepL refuses a glossary without a name, and the
     * stored one cut to the maximum length otherwise.
     */
    public function fitStoredName(string $storedName, int $pageId): string
    {
        $storedName = trim($storedName);

        return $storedName !== '' ? $this->cutToMaximumLength($storedName) : $this->getGlossaryName($pageId);
    }

    /**
     * Unlike a term, a name may be cut: it only labels the glossary. mb_substr() never splits a
     * multibyte character.
     */
    private function cutToMaximumLength(string $glossaryName): string
    {
        return rtrim(mb_substr($glossaryName, 0, self::MAX_NAME_LENGTH));
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
