<?php

declare(strict_types=1);

namespace LeightonThomas\Validation\Plugin\Rule\Arrays;

use LeightonThomas\Validation\Rule\Arrays\IsArray;
use LeightonThomas\Validation\Rule\Arrays\IsDefinedArray;
use Psalm\Plugin\EventHandler\Event\MethodReturnTypeProviderEvent;
use Psalm\Plugin\EventHandler\MethodReturnTypeProviderInterface;
use Psalm\Type;
use Psalm\Type\Union;
use Throwable;

use function array_flip;
use function array_key_exists;
use function array_key_first;
use function array_keys;
use function count;
use function get_class;
use function gettype;
use function in_array;
use function var_dump;

/**
 * @internal
 */
class IsDefinedArrayReturnTypeProvider implements MethodReturnTypeProviderInterface
{

    public static function getClassLikeNames(): array
    {
        return ['LeightonThomas\Validation\Rule\Arrays\IsDefinedArray'];
    }

    public static function getMethodReturnType(MethodReturnTypeProviderEvent $event): ?Union
    {
        $methodNameLower = $event->getMethodNameLowercase();
        $source = $event->getSource();
        $callArgs = $event->getCallArgs();
        $fqClasslikeName = $event->getFqClasslikeName();

        if (! in_array($methodNameLower, ['of', 'ofmaybe', 'and', 'andmaybe'])) {
            return null;
        }

        $firstArgNodeTypes = $source->getNodeTypeProvider()->getType($callArgs[0]->value);
        $secondArgNodeTypes = $source->getNodeTypeProvider()->getType($callArgs[1]->value);

        if ($firstArgNodeTypes === null || $secondArgNodeTypes === null) {
            return null;
        }

        $firstArgTypes = $firstArgNodeTypes->getAtomicTypes();
        $secondArgTypes = $secondArgNodeTypes->getAtomicTypes();

        $secondArgType = $secondArgTypes[array_key_first($secondArgTypes)];

        if (! $secondArgType instanceof Type\Atomic\TNamedObject) {
            return null;
        }

        try {
            /** @psalm-suppress InternalMethod $secondArgClass can't find another way to get this */
            $secondArgClass = $source->getCodebase()->classlike_storage_provider->get($secondArgType->value);
        } catch (Throwable $t) {
            return null;
        }

        if (! array_key_exists('leightonthomas\validation\rule\rule', $secondArgClass->parent_classes)) {
            return null;
        }

        $oRuleOverrides = (
            $secondArgClass->template_extended_params ?? []
        )['LeightonThomas\Validation\Rule\Rule']["O"] ?? null;
        if ($oRuleOverrides === null) {
            return null;
        }

        $secondArgRuleOutputType = $oRuleOverrides;
        if ($secondArgType instanceof Type\Atomic\TGenericObject) {
            // needs special handling because it's like a weird double nested thing
            if ($secondArgClass->name === IsArray::class) {
                $secondArgRuleOutputType = new Union(
                    [
                        new Type\Atomic\TArray($secondArgType->type_params)
                    ],
                );
            } else {
                $oRuleOverrideType = $oRuleOverrides->getTemplateTypes()[0] ?? null;
                if ($oRuleOverrideType === null) {
                    return null;
                }

                /** @var int|null $realTypeParameterIndex */
                $realTypeParameterIndex = array_flip(
                    array_keys($secondArgClass->template_types ?? [])
                )[$oRuleOverrideType->param_name] ?? null;

                if ($realTypeParameterIndex === null) {
                    return null;
                }

                $secondArgRuleOutputType = $secondArgType->type_params[$realTypeParameterIndex] ?? null;
                if ($secondArgRuleOutputType === null) {
                    return null;
                }
            }
        }

        $firstArgType = $firstArgTypes[array_key_first($firstArgTypes)];
        if (
            ! ($firstArgType instanceof Type\Atomic\TLiteralInt) &&
            ! ($firstArgType instanceof Type\Atomic\TLiteralString)
        ) {
            return null;
        }

        if (in_array($methodNameLower, ['of', 'ofmaybe'])) {
            $outputType = clone $secondArgRuleOutputType;

            if ($methodNameLower === 'ofmaybe') {
                $outputType->possibly_undefined = true;
            }

            $classLike = new Type\Atomic\TGenericObject(
                $fqClasslikeName,
                [
                    new Union(
                        [
                            new Type\Atomic\TKeyedArray(
                                [
                                    $firstArgType->value => $outputType,
                                ]
                            ),
                        ],
                    ),
                ],
            );

            return new Union([$classLike]);
        }

        $existingType = $event->getTemplateTypeParameters()[0] ?? null;
        if ($existingType === null) {
            return null;
        }

        $existingArray = $existingType->getAtomicTypes()['array'];
        if (! ($existingArray instanceof Type\Atomic\TKeyedArray)) {
            return null;
        }

        $outputType = clone $secondArgRuleOutputType;
        if ($methodNameLower === 'andmaybe') {
            $outputType->possibly_undefined = true;
        }

        $existingArray->properties[$firstArgType->value] = $outputType;

        $classLike = new Type\Atomic\TGenericObject(
            $fqClasslikeName,
            [$existingType],
        );

        return new Union([$classLike]);
    }
}
