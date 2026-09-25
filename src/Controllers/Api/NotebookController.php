<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\Notebook\Controllers\Api;

use kintai\Core\Api\Paginator;
use kintai\Core\Auth\PermissionService;
use kintai\Core\Repositories\NotebookEntryRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;

final class NotebookController
{
    public function __construct(
        private readonly NotebookEntryRepositoryInterface $notes,
        private readonly PermissionService $permissions,
    ) {}

    /** GET /api/v1/notebook-entries?store_id=X&page=1&limit=20 */
    public function index(Request $request): Response
    {
        [$page, $limit] = Paginator::params($request);
        $storeId = $request->query('store_id');

        $items = $storeId !== null
            ? $this->notes->findByStore((int) $storeId)
            : $this->notes->findAll();

        $items = $this->permissions->restrictToScope($this->authUser($request), 'notebook.view', $items);

        return Response::json(Paginator::paginate($items, $page, $limit));
    }

    /** GET /api/v1/notebook-entries/{id} */
    public function show(Request $request): Response
    {
        $item = $this->requireEntry($request, 'notebook.view');
        return Response::json($item);
    }

    /** POST /api/v1/notebook-entries */
    public function store(Request $request): Response
    {
        $authUser = $this->authUser($request);
        $data = array_merge($request->json() ?? [], [
            'author_id'  => (int) ($authUser['id'] ?? 0),
            'pinned'     => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        return Response::json($this->notes->save($data), 201);
    }

    /** PUT /api/v1/notebook-entries/{id} */
    public function update(Request $request): Response
    {
        $item = $this->requireEntry($request, 'notebook.create');
        $id   = (int) $item['id'];
        $data = array_merge($request->json() ?? [], ['id' => $id]);
        unset($data['author_id']);
        return Response::json($this->notes->save($data));
    }

    /** DELETE /api/v1/notebook-entries/{id} */
    public function destroy(Request $request): Response
    {
        $item = $this->requireEntry($request, 'notebook.create');
        $this->notes->delete((int) $item['id']);
        return Response::empty();
    }

    private function authUser(Request $request): array
    {
        return $request->getAttribute('auth_user') ?? [];
    }

    /** Charge la note par id et vérifie $permissionKey OU l'auteur sur son store réel. */
    private function requireEntry(Request $request, string $permissionKey): array
    {
        $authUser = $this->authUser($request);
        $id       = (int) $request->param('id');
        $item     = $this->notes->findById($id);

        if ($item === null) {
            throw new \kintai\Core\Exceptions\NotFoundException('Note introuvable.');
        }

        $isAuthor = (int) $item['author_id'] === (int) ($authUser['id'] ?? 0);
        $storeId  = $item['store_id'] !== null ? (int) $item['store_id'] : null;
        if (!$isAuthor && !$this->permissions->can($authUser, $permissionKey, $storeId)) {
            throw new \kintai\Core\Exceptions\ForbiddenException('Accès refusé.');
        }

        return $item;
    }
}
