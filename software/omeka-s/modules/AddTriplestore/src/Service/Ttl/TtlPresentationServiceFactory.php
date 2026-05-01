<?php

declare(strict_types=1);

namespace AddTriplestore\Service\Ttl;

use AddTriplestore\Service\GraphDbHttpService;
use AddTriplestore\Service\MegalodConfig;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

final class TtlPresentationServiceFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $megalod = $container->get(MegalodConfig::class);

        return new TtlPresentationService(
            $container->get(GraphDbHttpService::class),
            $container->get(TtlUriHelper::class),
            $megalod->getMegalodPublicBaseUri(),
            $megalod->getMegalodLocalBaseUri()
        );
    }
}
