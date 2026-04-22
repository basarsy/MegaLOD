<?php

namespace AddTriplestore\Service;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class GraphDbHttpServiceFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new GraphDbHttpService(
            $container->get(MegalodConfig::class),
            $container->get(GraphDbCredentialService::class)
        );
    }
}
