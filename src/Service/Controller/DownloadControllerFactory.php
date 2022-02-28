<?php declare(strict_types=1);

namespace Statistics\Service\Controller;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Statistics\Controller\DownloadController;

class DownloadControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $config = $services->get('Config');
        $basePath = $config['file_store']['local']['base_path'] ?: OMEKA_PATH . '/files';
        $adapterManager = $services->get('Omeka\ApiAdapterManager');
        return new DownloadController(
            $adapterManager->get('media'),
            $adapterManager->get('hits'),
            $basePath
        );
    }
}
