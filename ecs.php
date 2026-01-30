<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\Import\NoUnusedImportsFixer;
use PhpCsFixer\Fixer\ControlStructure\YodaStyleFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;
use Symplify\CodingStandard\Fixer\ArrayNotation\StandaloneLineInMultilineArrayFixer;

return ECSConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])

    /** @phpstan-ignore-next-line */
    ->withRules([
        /** @phpstan-ignore-next-line */
        NoUnusedImportsFixer::class,
    ])

    ->withConfiguredRule(
        /** @phpstan-ignore-next-line */
        YodaStyleFixer::class,
        [
            'equal' => true,
            'identical' => true,
            'less_and_greater' => true,
        ],
    )

    ->withPreparedSets(
        spaces: true,
        namespaces: true,
        docblocks: true,
        arrays: true,
        comments: true,
        cleanCode: true,
        strict: true,
        controlStructures: true,

    )

    ->withSkip([
        StandaloneLineInMultilineArrayFixer::class,
    ])
;
