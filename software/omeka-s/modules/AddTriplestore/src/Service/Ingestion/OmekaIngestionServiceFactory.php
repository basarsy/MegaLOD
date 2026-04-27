<?php

namespace AddTriplestore\Service\Ingestion;

use AddTriplestore\Service\MegalodConfig;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class OmekaIngestionServiceFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get(MegalodConfig::class);

        return new OmekaIngestionService(
            $config->getMegalodLocalBaseUri(),
            $container->get(OmekaResourceLookupService::class)
        );
    }
}
