<?php

declare(strict_types=1);

namespace AddTriplestore\Service;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

final class OmekaSiteResourceServiceFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new OmekaSiteResourceService($container->get('Omeka\ApiManager'));
    }
}
