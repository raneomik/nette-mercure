<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\Import\NoUnusedImportsFixer;
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
