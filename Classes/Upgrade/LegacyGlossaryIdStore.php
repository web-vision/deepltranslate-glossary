<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Upgrade;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Registry;

/**
 * Keeps the ids of the glossaries of the DeepL glossary API v2 which
 * {@see MigrateToMultilingualGlossaryWizard} left at DeepL, until
 * `deepl:glossary:cleanup --legacy` removes them.
 *
 * The wizard does not delete them itself: a copy of the database, for example on a staging
 * system sharing the API key, would otherwise delete the glossaries the live system still uses.
 *
 * @internal Only for the upgrade wizard and the cleanup command of this extension.
 */
#[Autoconfigure(public: true)]
final readonly class LegacyGlossaryIdStore
{
    private const REGISTRY_NAMESPACE = 'deepltranslate_glossary';
    private const REGISTRY_KEY = 'legacyGlossaryIds';

    public function __construct(
        private Registry $registry,
    ) {
    }

    /**
     * @return list<string>
     */
    public function getGlossaryIds(): array
    {
        $glossaryIds = $this->registry->get(self::REGISTRY_NAMESPACE, self::REGISTRY_KEY, []);
        if (!is_array($glossaryIds)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $glossaryId): string => is_string($glossaryId) ? $glossaryId : '', $glossaryIds),
            static fn (string $glossaryId): bool => $glossaryId !== ''
        ));
    }

    /**
     * @param string[] $glossaryIds
     */
    public function add(array $glossaryIds): void
    {
        $this->store([...$this->getGlossaryIds(), ...$glossaryIds]);
    }

    /**
     * @param string[] $glossaryIds
     */
    public function remove(array $glossaryIds): void
    {
        $this->store(array_diff($this->getGlossaryIds(), $glossaryIds));
    }

    /**
     * @param string[] $glossaryIds
     */
    private function store(array $glossaryIds): void
    {
        $glossaryIds = array_values(array_unique(array_filter(
            $glossaryIds,
            static fn (string $glossaryId): bool => $glossaryId !== ''
        )));
        if ($glossaryIds === []) {
            $this->registry->remove(self::REGISTRY_NAMESPACE, self::REGISTRY_KEY);
            return;
        }
        $this->registry->set(self::REGISTRY_NAMESPACE, self::REGISTRY_KEY, $glossaryIds);
    }
}
