<?php

declare(strict_types=1);

namespace AddTriplestore\Service;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

final class SiteUserAuthSupportFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new SiteUserAuthSupport($container->get('Omeka\Connection'));
    }
}
