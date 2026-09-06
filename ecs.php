<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\Basic\SingleLineEmptyBodyFixer;
use PhpCsFixer\Fixer\ControlStructure\YodaStyleFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocAlignFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocToCommentFixer;
use PhpCsFixer\Fixer\PhpUnit\PhpUnitInternalClassFixer;
use PhpCsFixer\Fixer\PhpUnit\PhpUnitTestAnnotationFixer;
use PhpCsFixer\Fixer\PhpUnit\PhpUnitTestClassRequiresCoversFixer;
use Symplify\CodingStandard\Fixer\ArrayNotation\StandaloneLineInMultilineArrayFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
    ->withPaths([
        __DIR__.'/src',
        __DIR__.'/tests',
        __DIR__.'/ecs.php',
        __DIR__.'/rector.php',
    ])

    ->withPreparedSets(
        arrays: true,
        comments: true,
        docblocks: true,
        spaces: true,
        namespaces: true,
        controlStructures: true,
        cleanCode: true,
    )

    ->withPhpCsFixerSets(
        phpCsFixer: true,
        phpCsFixerRisky: true,
    )

    ->withConfiguredRule(
        YodaStyleFixer::class, // @phpstan-ignore-line
        [
            'equal' => true,
            'identical' => true,
            'less_and_greater' => true,
        ],
    )
    ->withConfiguredRule(
        PhpdocAlignFixer::class, // @phpstan-ignore-line
        [
            'align' => 'left',
        ],
    )
    ->withConfiguredRule(
        PhpdocToCommentFixer::class, // @phpstan-ignore-line
        [
            'ignored_tags' => ['todo', 'var'],
        ],
    )

    ->withSkip([
        StandaloneLineInMultilineArrayFixer::class,
        SingleLineEmptyBodyFixer::class, // @phpstan-ignore-line
        PhpUnitInternalClassFixer::class, // @phpstan-ignore-line
        PhpUnitTestAnnotationFixer::class, // @phpstan-ignore-line
        PhpUnitTestClassRequiresCoversFixer::class, // @phpstan-ignore-line
    ])
;
