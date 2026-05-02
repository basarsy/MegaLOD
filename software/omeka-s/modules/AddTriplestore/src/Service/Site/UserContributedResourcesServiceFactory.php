<?php

declare(strict_types=1);

namespace AddTriplestore\Service\Site;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

final class UserContributedResourcesServiceFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new UserContributedResourcesService(
            $container->get('Omeka\ApiManager'),
            $container->get(ArrowheadItemClassifier::class)
        );
    }
}
