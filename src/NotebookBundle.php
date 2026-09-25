<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\Notebook;

use kintai\Core\BundleContract\Bundle;
use kintai\Core\Repositories\NotebookEntryRepositoryInterface;
use kintai\Core\Repositories\DatabaseNotebookEntryRepository;

final class NotebookBundle extends Bundle
{
    public function getName(): string
    {
        return 'notebook';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getLabel(): string
    {
        return __('bundle_notebook');
    }

    public function getDescription(): string
    {
        return __('bundle_notebook_desc');
    }

    public function register(): void
    {
        $this->registerServices();
        $this->loadViewsFrom($this->getPath() . '/Views', 'notebook');
        $this->loadRoutesFrom($this->getPath() . '/routes.php');
    }

    private function registerServices(): void
    {
        $container = $this->app->container();

        $container->singleton(
            NotebookEntryRepositoryInterface::class,
            fn() => new DatabaseNotebookEntryRepository()
        );
    }
}
