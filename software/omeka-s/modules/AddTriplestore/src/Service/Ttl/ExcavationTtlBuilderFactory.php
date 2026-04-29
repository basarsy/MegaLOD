<?php

namespace AddTriplestore\Service\Ttl;

use AddTriplestore\Service\MegalodConfig;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ExcavationTtlBuilderFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get(MegalodConfig::class);

        return new ExcavationTtlBuilder(
            $container->get(TtlUriHelper::class),
            $config->getMegalodPublicBaseUri()
        );
    }
}
