<?php

declare(strict_types=1);

namespace WebVision\Deepltranslate\Glossary\Command;

use WebVision\Deepltranslate\Glossary\Domain\Repository\GlossaryRepository;
use WebVision\Deepltranslate\Glossary\Service\DeeplGlossaryService;

trait GlossaryCommandTrait
{
    protected DeeplGlossaryService $deeplGlossaryService;

    protected GlossaryRepository $glossaryRepository;

    public function injectDeeplGlossaryService(DeeplGlossaryService $deeplGlossaryService): void
    {
        $this->deeplGlossaryService = $deeplGlossaryService;
    }

    public function injectGlossaryRepository(GlossaryRepository $glossaryRepository): void
    {
        $this->glossaryRepository = $glossaryRepository;
    }
}
