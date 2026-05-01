<?php

declare(strict_types=1);

namespace AddTriplestore\Service\Ingestion;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

final class CollectingFormToExcavationDataMapperFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new CollectingFormToExcavationDataMapper($container->get(OmekaResourceLookupService::class));
    }
}
