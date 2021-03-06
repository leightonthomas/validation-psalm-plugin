<?php

declare(strict_types=1);

namespace LeightonThomas\Validation\Plugin;

use Psalm\Plugin\PluginEntryPointInterface;
use Psalm\Plugin\RegistrationInterface;
use SimpleXMLElement;

use function class_exists;

/**
 * @internal
 */
class Plugin implements PluginEntryPointInterface
{

    public function __invoke(RegistrationInterface $registration, ?SimpleXMLElement $config = null): void
    {
        // This seems to be required to auto-loaded the class, based on what's in an official Psalm plugin
        class_exists(Rule\Arrays\IsDefinedArrayReturnTypeProvider::class, true);
        $registration->registerHooksFromClass(Rule\Arrays\IsDefinedArrayReturnTypeProvider::class);
    }
}
