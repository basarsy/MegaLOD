<?php

declare(strict_types=1);

namespace AddTriplestore\Service\Ttl;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

final class VocabularyLabelServiceFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new VocabularyLabelService();
    }
}
