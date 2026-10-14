<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\Notebook;

use kintai\Contract\Bundle;
use kintai\Contract\Extension\StoreFeature;
use kintai\Core\Repositories\DatabaseNotebookEntryRepository;
use kintai\Core\Repositories\NotebookEntryRepositoryInterface;

final class NotebookBundle extends Bundle
{
    public function getName(): string
    {
        return 'notebook';
    }

    public function getVersion(): string
    {
        return '1.2.0';
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
        $this->loadAssetsFrom('public');
    }

    private function registerServices(): void
    {
        $container = $this->app->container();

        $container->singleton(
            NotebookEntryRepositoryInterface::class,
            fn() => new DatabaseNotebookEntryRepository()
        );
    }

    /**
     * Catégories de permissions (contrat 1.2), proposées sur la page des rôles.
     */
    public function permissions(): array
    {
        return ['notebook' => ['view', 'create', 'manage']];
    }

    /**
     * Fonctionnalité activable par magasin (contrat 1.3), sur la fiche magasin.
     */
    public function storeFeatures(): array
    {
        return [new StoreFeature('notes', 'feature_notes', 'feature_notes_desc', '📝', 90)];
    }
}
