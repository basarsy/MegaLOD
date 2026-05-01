<?php

namespace AddTriplestore\Service\Encounter;

use AddTriplestore\Service\ExcavationItemSetContextService;
use AddTriplestore\Service\GraphDbHttpService;
use AddTriplestore\Service\MegalodConfig;
use AddTriplestore\Service\Ttl\TtlUriHelper;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

final class EncounterEventServiceFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        /** @var MegalodConfig $megalod */
        $megalod = $container->get(MegalodConfig::class);

        return new EncounterEventService(
            $container->get('Omeka\ApiManager'),
            $container->get(GraphDbHttpService::class),
            $container->get(ExcavationItemSetContextService::class),
            $container->get(TtlUriHelper::class),
            $megalod->getMegalodPublicBaseUri(),
            $megalod->getMegalodLocalBaseUri()
        );
    }
}
