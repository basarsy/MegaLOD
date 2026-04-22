<?php

namespace AddTriplestore\Service\Ttl;

use AddTriplestore\Service\MegalodConfig;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class TtlUriNormalizerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new TtlUriNormalizer($container->get(MegalodConfig::class));
    }
}
