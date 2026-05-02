<?php

declare(strict_types=1);

namespace AddTriplestore\Service\Site;

use AddTriplestore\Service\Ttl\VocabularyLabelService;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

final class ResourceDetailPresentationServiceFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new ResourceDetailPresentationService(
            $container->get('Omeka\ApiManager'),
            $container->get(VocabularyLabelService::class)
        );
    }
}
