<?php

namespace AddTriplestore\Service\Ttl;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class TtlContentInspectionServiceFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new TtlContentInspectionService();
    }
}
