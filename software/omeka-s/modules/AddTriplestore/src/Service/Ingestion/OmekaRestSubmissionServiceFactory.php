<?php

namespace AddTriplestore\Service\Ingestion;

use AddTriplestore\Service\OmekaApiCredentialService;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class OmekaRestSubmissionServiceFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new OmekaRestSubmissionService(
            $container->get(OmekaApiCredentialService::class),
            $container->get(OmekaResourceLookupService::class),
            $container->get('Omeka\ApiManager')
        );
    }
}
