<?php

declare(strict_types=1);

namespace LeightonThomas\Validation\Plugin\Rule\Arrays;

use Psalm\CodeLocation;
use Psalm\Context;
use Psalm\Plugin\Hook\MethodReturnTypeProviderInterface;
use Psalm\StatementsSource;
use Psalm\Type;
use Psalm\Type\Union;
use Throwable;

use function array_flip;
use function array_key_exists;
use function array_key_first;
use function array_keys;
use function in_array;

/**
 * @internal
 */
class IsDefinedArrayReturnTypeProvider implements MethodReturnTypeProviderInterface
{

    public static function getClassLikeNames(): array
    {
        return ['LeightonThomas\Validation\Rule\Arrays\IsDefinedArray'];
    }

    public static function getMethodReturnType(
        StatementsSource $source,
        string $fq_classlike_name,
        string $method_name_lowercase,
        array $call_args,
        Context $context,
        CodeLocation $code_location,
        array $template_type_parameters = null,
        string $called_fq_classlike_name = null,
        string $called_method_name_lowercase = null
    ): ?Union {
        if (! in_array($method_name_lowercase, ['of', 'ofmaybe', 'and', 'andmaybe'])) {
            return null;
        }

        $firstArgNodeTypes = $source->getNodeTypeProvider()->getType($call_args[0]->value);
        $secondArgNodeTypes = $source->getNodeTypeProvider()->getType($call_args[1]->value);

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
                $secondArgClass->template_type_extends ?? []
            )['LeightonThomas\Validation\Rule\Rule']["O"] ?? null;
        if ($oRuleOverrides === null) {
            return null;
        }

        if ($secondArgType instanceof Type\Atomic\TGenericObject) {
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
        } else {
            $secondArgRuleOutputType = $oRuleOverrides;
        }

        $firstArgType = $firstArgTypes[array_key_first($firstArgTypes)];
        if (
            ! ($firstArgType instanceof Type\Atomic\TLiteralInt) &&
            ! ($firstArgType instanceof Type\Atomic\TLiteralString)
        ) {
            return null;
        }

        if (in_array($method_name_lowercase, ['of', 'ofmaybe'])) {
            $outputType = clone $secondArgRuleOutputType;

            if ($method_name_lowercase === 'ofmaybe') {
                $outputType->possibly_undefined = true;
            }

            $classLike = new Type\Atomic\TGenericObject(
                $fq_classlike_name,
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

        /** @var Union|null $existingType */
        $existingType = $template_type_parameters[0] ?? null;
        if ($existingType === null) {
            return null;
        }

        $existingArray = $existingType->getAtomicTypes()['array'];
        if (! ($existingArray instanceof Type\Atomic\TKeyedArray)) {
            return null;
        }

        $outputType = clone $secondArgRuleOutputType;
        if ($method_name_lowercase === 'andmaybe') {
            $outputType->possibly_undefined = true;
        }

        $existingArray->properties[$firstArgType->value] = $outputType;

        $classLike = new Type\Atomic\TGenericObject(
            $fq_classlike_name,
            [$existingType],
        );

        return new Union([$classLike]);
    }
}
