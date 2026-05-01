<?php

namespace AddTriplestore\Service\Ingestion;

use AddTriplestore\Service\ExcavationItemSetContextService;
use AddTriplestore\Service\MegalodConfig;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class CollectingFormToArrowheadMapperFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new CollectingFormToArrowheadMapper(
            $container->get(ExcavationItemSetContextService::class),
            $container->get(MegalodConfig::class)->getMegalodLocalBaseUri()
        );
    }
}
