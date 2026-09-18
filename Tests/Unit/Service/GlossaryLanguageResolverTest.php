<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use WebVision\Deepltranslate\Glossary\Domain\Dto\GlossaryLanguageCollision;
use WebVision\Deepltranslate\Glossary\Domain\Dto\GlossaryLanguageCollisionReason;
use WebVision\Deepltranslate\Glossary\Service\GlossaryLanguageResolver;

final class GlossaryLanguageResolverTest extends UnitTestCase
{
    public static function resolveDataProvider(): \Generator
    {
        yield 'languages with distinct codes are used without collision' => [
            'languages' => [
                1 => [
                    'locale' => 'fr_FR.UTF-8',
                ],
            ],
            'termCountByLanguageId' => [
                0 => 2,
                1 => 2,
            ],
            'expectedLanguageIdsByCode' => [
                'de' => 0,
                'fr' => 1,
            ],
            'expectedCollisions' => [],
        ];
        yield 'default language owns its code without a warning' => [
            'languages' => [
                1 => [
                    'locale' => 'de_AT.UTF-8',
                ],
            ],
            'termCountByLanguageId' => [
                0 => 2,
                1 => 2,
            ],
            'expectedLanguageIdsByCode' => [
                'de' => 0,
            ],
            'expectedCollisions' => [],
        ];
        yield 'default language owns its code even against a preferred language' => [
            'languages' => [
                1 => [
                    'locale' => 'de_AT.UTF-8',
                    'deeplGlossaryTerms' => 'preferred',
                ],
            ],
            'termCountByLanguageId' => [
                0 => 2,
                1 => 2,
            ],
            'expectedLanguageIdsByCode' => [
                'de' => 0,
            ],
            'expectedCollisions' => [
                [
                    'languageCode' => 'de',
                    'reason' => GlossaryLanguageCollisionReason::DefaultLanguageOwnsCode,
                    'selectedLanguageId' => 0,
                    'ignoredLanguageIds' => [1],
                ],
            ],
        ];
        yield 'lowest language id with terms wins without a preferred language' => [
            'languages' => [
                1 => [
                    'locale' => 'en_GB.UTF-8',
                ],
                2 => [
                    'locale' => 'en_US.UTF-8',
                ],
            ],
            'termCountByLanguageId' => [
                0 => 2,
                1 => 2,
                2 => 1,
            ],
            'expectedLanguageIdsByCode' => [
                'de' => 0,
                'en' => 1,
            ],
            'expectedCollisions' => [
                [
                    'languageCode' => 'en',
                    'reason' => GlossaryLanguageCollisionReason::NoPreferredLanguage,
                    'selectedLanguageId' => 1,
                    'ignoredLanguageIds' => [2],
                ],
            ],
        ];
        yield 'language without terms is skipped silently' => [
            'languages' => [
                1 => [
                    'locale' => 'en_US.UTF-8',
                ],
                2 => [
                    'locale' => 'en_GB.UTF-8',
                ],
            ],
            'termCountByLanguageId' => [
                0 => 2,
                2 => 2,
            ],
            'expectedLanguageIdsByCode' => [
                'de' => 0,
                'en' => 2,
            ],
            'expectedCollisions' => [],
        ];
        yield 'single preferred language wins silently' => [
            'languages' => [
                1 => [
                    'locale' => 'en_GB.UTF-8',
                ],
                2 => [
                    'locale' => 'en_US.UTF-8',
                    'deeplGlossaryTerms' => 'preferred',
                ],
            ],
            'termCountByLanguageId' => [
                0 => 2,
                1 => 2,
                2 => 1,
            ],
            'expectedLanguageIdsByCode' => [
                'de' => 0,
                'en' => 2,
            ],
            'expectedCollisions' => [],
        ];
        yield 'preferred language without terms still wins' => [
            'languages' => [
                1 => [
                    'locale' => 'pt_PT.UTF-8',
                ],
                2 => [
                    'locale' => 'pt_BR.UTF-8',
                    'deeplGlossaryTerms' => 'preferred',
                ],
            ],
            'termCountByLanguageId' => [
                0 => 2,
                1 => 2,
            ],
            'expectedLanguageIdsByCode' => [
                'de' => 0,
                'pt' => 2,
            ],
            'expectedCollisions' => [],
        ];
        yield 'several preferred languages use the lowest language id' => [
            'languages' => [
                1 => [
                    'locale' => 'en_GB.UTF-8',
                    'deeplGlossaryTerms' => 'preferred',
                ],
                2 => [
                    'locale' => 'en_US.UTF-8',
                    'deeplGlossaryTerms' => 'preferred',
                ],
                3 => [
                    'locale' => 'en_IE.UTF-8',
                ],
            ],
            'termCountByLanguageId' => [
                0 => 2,
                1 => 2,
                2 => 2,
                3 => 2,
            ],
            'expectedLanguageIdsByCode' => [
                'de' => 0,
                'en' => 1,
            ],
            'expectedCollisions' => [
                [
                    'languageCode' => 'en',
                    'reason' => GlossaryLanguageCollisionReason::MultiplePreferredLanguages,
                    'selectedLanguageId' => 1,
                    'ignoredLanguageIds' => [2],
                ],
            ],
        ];
        yield 'automatic value behaves like a missing value' => [
            'languages' => [
                1 => [
                    'locale' => 'en_GB.UTF-8',
                    'deeplGlossaryTerms' => 'automatic',
                ],
                2 => [
                    'locale' => 'en_US.UTF-8',
                    'deeplGlossaryTerms' => 'automatic',
                ],
            ],
            'termCountByLanguageId' => [
                0 => 2,
                1 => 2,
                2 => 2,
            ],
            'expectedLanguageIdsByCode' => [
                'de' => 0,
                'en' => 1,
            ],
            'expectedCollisions' => [
                [
                    'languageCode' => 'en',
                    'reason' => GlossaryLanguageCollisionReason::NoPreferredLanguage,
                    'selectedLanguageId' => 1,
                    'ignoredLanguageIds' => [2],
                ],
            ],
        ];
    }

    /**
     * @param array<int, array<string, string>> $languages
     * @param array<int, int> $termCountByLanguageId
     * @param array<string, int> $expectedLanguageIdsByCode
     * @param list<array{languageCode: string, reason: GlossaryLanguageCollisionReason, selectedLanguageId: int, ignoredLanguageIds: list<int>}> $expectedCollisions
     */
    #[Test]
    #[DataProvider('resolveDataProvider')]
    public function resolveSelectsOneSiteLanguagePerLanguageCode(
        array $languages,
        array $termCountByLanguageId,
        array $expectedLanguageIdsByCode,
        array $expectedCollisions,
    ): void {
        $languageConfiguration = [
            [
                'languageId' => 0,
                'title' => 'Deutsch',
                'locale' => 'de_DE.UTF-8',
                'base' => '/',
            ],
        ];
        foreach ($languages as $languageId => $language) {
            $languageConfiguration[] = array_merge(
                [
                    'languageId' => $languageId,
                    'title' => 'Language ' . $languageId,
                    'base' => '/' . $languageId . '/',
                ],
                $language
            );
        }
        $site = new Site('acme', 1, [
            'base' => '/',
            'languages' => $languageConfiguration,
        ]);

        $selection = (new GlossaryLanguageResolver())->resolve($site, $termCountByLanguageId);

        self::assertSame($expectedLanguageIdsByCode, $selection->languageIdsByCode);
        self::assertSame($expectedCollisions, array_map(
            static fn (GlossaryLanguageCollision $collision): array => [
                'languageCode' => $collision->languageCode,
                'reason' => $collision->reason,
                'selectedLanguageId' => $collision->selectedLanguage->getLanguageId(),
                'ignoredLanguageIds' => array_map(
                    static fn (SiteLanguage $language): int => $language->getLanguageId(),
                    $collision->ignoredLanguages
                ),
            ],
            $selection->collisions
        ));
    }
}
