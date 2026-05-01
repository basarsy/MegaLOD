<?php

declare(strict_types=1);

namespace AddTriplestore\Service\Ttl;

use AddTriplestore\Service\MegalodConfig;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

final class ArrowheadCanonicalTtlExporterFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new ArrowheadCanonicalTtlExporter(
            $container->get(MegalodConfig::class),
            $container->get(TtlUriHelper::class)
        );
    }
}
