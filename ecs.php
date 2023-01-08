<?php

declare(strict_types=1);

/*
 * This file is part of the "t3_tactician" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

use PhpCsFixer\Fixer\ArrayNotation\ArraySyntaxFixer;
use PhpCsFixer\Fixer\Comment\HeaderCommentFixer;
use PhpCsFixer\Fixer\ControlStructure\YodaStyleFixer;
use PhpCsFixer\Fixer\Phpdoc\GeneralPhpdocAnnotationRemoveFixer;
use PhpCsFixer\Fixer\Phpdoc\NoSuperfluousPhpdocTagsFixer;
use PhpCsFixer\Fixer\Strict\DeclareStrictTypesFixer;
use Symplify\CodingStandard\Fixer\ArrayNotation\ArrayOpenerAndCloserNewlineFixer;
use Symplify\CodingStandard\Fixer\ArrayNotation\StandaloneLineInMultilineArrayFixer;
use Symplify\CodingStandard\Fixer\LineLength\LineLengthFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;
use Symplify\EasyCodingStandard\ValueObject\Set\SetList;

return static function (ECSConfig $ecsConfig): void {
    $header = <<<CODE_SAMPLE
This file is part of the "t3_tactician" Extension for TYPO3 CMS.

For the full copyright and license information, please read the
LICENSE.txt file that was distributed with this source code.
CODE_SAMPLE;

    $ecsConfig->paths([
        __DIR__ . '/Classes',
        __DIR__ . '/Tests',
        __DIR__ . '/Configuration',
        __DIR__ . '/ecs.php',
        __DIR__ . '/rector.php',
    ]);

    $ecsConfig->ruleWithConfiguration(ArraySyntaxFixer::class, [
        'syntax' => 'short',
    ]);
    $ecsConfig->rule(DeclareStrictTypesFixer::class);
    $ecsConfig->rule(LineLengthFixer::class);
    $ecsConfig->rule(YodaStyleFixer::class);
    $ecsConfig->ruleWithConfiguration(HeaderCommentFixer::class, [
        'header' => $header,
        'separate' => 'both',
    ]);

    $ecsConfig->rule(StandaloneLineInMultilineArrayFixer::class);
    $ecsConfig->rule(ArrayOpenerAndCloserNewlineFixer::class);

    $ecsConfig->ruleWithConfiguration(
        GeneralPhpdocAnnotationRemoveFixer::class,
        [
            'annotations' => ['throws', 'author', 'package', 'group'],
        ],
    );

    $ecsConfig->ruleWithConfiguration(NoSuperfluousPhpdocTagsFixer::class, [
        'allow_mixed' => true,
    ],);

    $ecsConfig->sets([SetList::PSR_12, SetList::SYMPLIFY, SetList::COMMON, SetList::CLEAN_CODE]);
};
