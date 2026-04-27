<?php

namespace AddTriplestore\Service\Ingestion;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class OmekaResourceLookupServiceFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new OmekaResourceLookupService(
            $container->get('Omeka\ApiManager')
        );
    }
}
