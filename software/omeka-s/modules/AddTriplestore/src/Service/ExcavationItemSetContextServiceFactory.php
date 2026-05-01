<?php

namespace AddTriplestore\Service;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ExcavationItemSetContextServiceFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new ExcavationItemSetContextService(
            $container->get('Omeka\Settings\Site'),
            $container->get('Omeka\ApiManager'),
            $container->get(GraphDbHttpService::class),
            $container->get(MegalodConfig::class)
        );
    }
}
