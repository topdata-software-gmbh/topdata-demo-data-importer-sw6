<?php

/**
 * Configuration file for Rector.
 *
 * This script configures the rules and sets for the Rector process,
 * defining which code transformations should be applied to the codebase.
 */
declare(strict_types=1);

use Rector\CodeQuality\Rector\BooleanAnd\RemoveUselessIsObjectCheckRector;
use Rector\CodeQuality\Rector\Class_\CompleteDynamicPropertiesRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\Property\RemoveUselessVarTagRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return static function (RectorConfig $rectorConfig): void {

    $rectorConfig->paths([
        __DIR__ . '/src',
        // Add other directories you want to scan here
    ]);

    $rectorConfig->skip([
        __DIR__ . '/vendor',
    ]);

    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_74,
//         SetList::TYPE_DECLARATION, // it breaks return with types for method overwrites in inherited classes
    ]);

    // ---- Apply specific rules for adding typed properties and property promotion
    $rectorConfig->rule(RemoveUselessVarTagRector::class);
    $rectorConfig->rule(RemoveUselessIsObjectCheckRector::class);
    $rectorConfig->rule(CompleteDynamicPropertiesRector::class);

    $rectorConfig->ruleWithConfiguration(\Rector\Php80\Rector\Class_\AnnotationToAttributeRector::class, [
        new \Rector\Php80\ValueObject\AnnotationToAttribute('Symfony\\Component\\Routing\\Annotation\\Route'),
    ]);

    $rectorConfig->rule(\Rector\Symfony\Symfony61\Rector\Class_\CommandPropertyToAttributeRector::class);
    $rectorConfig->rule(\Rector\Php70\Rector\ClassMethod\Php4ConstructorRector::class);
    $rectorConfig->rule(\Rector\Php82\Rector\FuncCall\Utf8DecodeEncodeToMbConvertEncodingRector::class);

};