<?php

namespace AddTriplestore\Service\Ttl;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class XmlToTtlPipelineFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new XmlToTtlPipeline(
            $container->get(TtlUriHelper::class)
        );
    }
}
