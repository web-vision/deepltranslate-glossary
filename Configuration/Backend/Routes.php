<?php

use WebVision\Deepltranslate\Glossary\Controller\GlossarySyncController;

return [
    'glossaryupdate' => [
        'path' => '/glossary',
        'target' => GlossarySyncController::class . '::update',
    ],
];
