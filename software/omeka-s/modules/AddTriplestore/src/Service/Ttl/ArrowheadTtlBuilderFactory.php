<?php

namespace AddTriplestore\Service\Ttl;

use AddTriplestore\Service\Ingestion\OmekaResourceLookupService;
use AddTriplestore\Service\MegalodConfig;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ArrowheadTtlBuilderFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get(MegalodConfig::class);

        return new ArrowheadTtlBuilder(
            $container->get(TtlUriHelper::class),
            $container->get(OmekaResourceLookupService::class),
            $config->getMegalodLocalBaseUri()
        );
    }
}
