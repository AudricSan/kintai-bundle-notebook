<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\Notebook\Controllers\Web;

use kintai\Bundles\Installed\Notebook\Controllers\Web\NotebookController;
use kintai\Core\Auth\PermissionService;
use kintai\Core\Exceptions\ForbiddenException;
use kintai\Core\Repositories\NotebookEntryRepositoryInterface;
use kintai\Core\Repositories\RoleAssignmentRepositoryInterface;
use kintai\Core\Repositories\RoleRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\AuditLogger;
use kintai\Core\Services\NotificationService;
use kintai\UI\ViewRenderer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class NotebookControllerTest extends TestCase
{
    private NotebookEntryRepositoryInterface&MockObject $notes;
    private StoreRepositoryInterface&MockObject $stores;
    private StoreUserRepositoryInterface&MockObject $storeUsers;
    private UserRepositoryInterface&MockObject $users;
    private NotificationService&MockObject $notifs;
    private RoleAssignmentRepositoryInterface&MockObject $assignments;
    private RoleRepositoryInterface&MockObject $roles;
    private NotebookController $controller;

    protected function setUp(): void
    {
        $this->notes       = $this->createMock(NotebookEntryRepositoryInterface::class);
        $this->stores      = $this->createMock(StoreRepositoryInterface::class);
        $this->storeUsers  = $this->createMock(StoreUserRepositoryInterface::class);
        $this->users       = $this->createMock(UserRepositoryInterface::class);
        $this->notifs      = $this->createMock(NotificationService::class);
        $this->assignments = $this->createMock(RoleAssignmentRepositoryInterface::class);
        $this->roles       = $this->createMock(RoleRepositoryInterface::class);

        $this->stores->method('findAll')->willReturn([['id' => 1, 'name' => 'Store 1'], ['id' => 2, 'name' => 'Store 2']]);
        // Pas de findAll() par défaut ici : plusieurs tests le reconfigurent avec une valeur
        // spécifique (ex. testStoreAllowsAnOrgWideNoteForAGloballyScopedUser), et empiler un
        // second stub inconditionnel sur le même mock est ambigu côté PHPUnit (le premier stub
        // sans contrainte peut rester actif) — vu ici en pratique : la valeur du setUp() gagnait
        // silencieusement sur celle du test, notifyMembers() itérait alors une liste vide.

        $this->controller = new NotebookController(
            new ViewRenderer(sys_get_temp_dir()),
            $this->notes,
            $this->stores,
            $this->storeUsers,
            $this->users,
            new AuditLogger(),
            $this->notifs,
            new PermissionService($this->assignments, $this->roles),
        );
    }

    /**
     * Rôle système (is_system) affecté en portée globale à $userId : accorde tout, dont
     * notebook.manage. willReturnCallback() plutôt que with($userId) : notifyMembers() en
     * portée organisation interroge can() pour CHAQUE utilisateur (ex. id 2 sans rôle),
     * qu'une contrainte with() stricte sur un seul id ferait échouer comme "invocation
     * inattendue" au lieu de simplement retourner [] pour les autres.
     */
    private function grantGlobalRole(int $userId): void
    {
        $this->assignments->method('findByUser')->willReturnCallback(
            fn(int $uid) => $uid === $userId
                ? [['id' => 1, 'user_id' => $userId, 'role_id' => 9, 'scope_type' => 'global', 'scope_id' => null]]
                : []
        );
        $this->roles->method('findById')->with(9)->willReturn(['id' => 9, 'is_system' => 1]);
    }

    private function grantStoreScopedRole(int $userId, int $storeId, array $permissions): void
    {
        $this->assignments->method('findByUser')->with($userId)->willReturn([
            ['id' => 1, 'user_id' => $userId, 'role_id' => 5, 'scope_type' => 'store', 'scope_id' => $storeId],
        ]);
        $this->roles->method('findById')->with(5)->willReturn(['id' => 5, 'is_system' => 0]);
        $this->roles->method('getPermissions')->with(5)->willReturn($permissions);
        $this->roles->method('getGlobalPermissionKeys')->with(5)->willReturn([]);
    }

    private function request(int $userId, ?array $managedStoreIds): Request
    {
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => $userId]);
        $req->setAttribute('managed_store_ids', $managedStoreIds);
        return $req;
    }

    private function redirectLocation(Response $response): string
    {
        $ref = new \ReflectionProperty(Response::class, 'headers');
        $ref->setAccessible(true);
        return $ref->getValue($response)['Location'] ?? '';
    }

    protected function tearDown(): void
    {
        $_POST = [];
    }

    // ── store() ──────────────────────────────────────────────────────────

    public function testStoreRejectsEmptyContent(): void
    {
        $this->grantGlobalRole(1);
        $_POST = ['content' => '   '];

        $this->notes->expects($this->never())->method('save');

        $response = $this->controller->store($this->request(1, null));

        $this->assertStringContainsString('error=empty_content', $this->redirectLocation($response));
    }

    public function testStoreCreatesAStoreScopedNoteWhenUserManagesThatStore(): void
    {
        $this->grantStoreScopedRole(9, 1, ['notebook.create', 'notebook.view']);
        $_POST = ['content' => 'Bonjour équipe', 'store_id' => '1'];

        $this->notes->method('save')->willReturn(['id' => 42]);
        $this->notes->expects($this->once())->method('save')->with($this->callback(
            fn(array $data) => $data['author_id'] === 9 && $data['store_id'] === 1 && $data['content'] === 'Bonjour équipe'
        ));

        $this->storeUsers->method('findByStore')->with(1)->willReturn([['user_id' => 9, 'store_id' => 1]]);
        $this->users->method('findById')->with(9)->willReturn(['id' => 9]);
        $this->notifs->expects($this->once())->method('notifyMany')->with([9], 'notebook_entry_created', 'notif_notebook_entry_created_body', [], 42);

        $response = $this->controller->store($this->request(9, [1]));

        $this->assertStringContainsString('success=created', $this->redirectLocation($response));
    }

    public function testStoreRejectsAStoreScopedNoteOnAStoreTheUserDoesNotManage(): void
    {
        $this->grantStoreScopedRole(9, 1, ['notebook.create']);
        $_POST = ['content' => 'Bonjour', 'store_id' => '2'];

        $this->notes->expects($this->never())->method('save');

        $this->expectException(ForbiddenException::class);
        $this->controller->store($this->request(9, [1]));
    }

    public function testStoreRejectsAnOrgWideNoteWhenUserIsNotGloballyScoped(): void
    {
        $this->grantStoreScopedRole(9, 1, ['notebook.create']);
        $_POST = ['content' => 'Pour tous', 'store_id' => ''];

        $this->notes->expects($this->never())->method('save');

        $response = $this->controller->store($this->request(9, [1]));

        $this->assertStringContainsString('error=forbidden', $this->redirectLocation($response));
    }

    public function testStoreAllowsAnOrgWideNoteForAGloballyScopedUser(): void
    {
        // Deux utilisateurs globalement scopés (ex. deux Owners) : les deux doivent être
        // notifiés, pas seulement l'auteur — vérifie que notifyMembers() ne s'arrête pas
        // au premier destinataire trouvé.
        $this->assignments->method('findByUser')->willReturnCallback(
            fn(int $uid) => [['id' => 1, 'user_id' => $uid, 'role_id' => 9, 'scope_type' => 'global', 'scope_id' => null]]
        );
        $this->roles->method('findById')->with(9)->willReturn(['id' => 9, 'is_system' => 1]);
        $_POST = ['content' => 'Pour tous'];

        $this->notes->method('save')->willReturn(['id' => 7]);
        $this->notes->expects($this->once())->method('save')->with($this->callback(
            fn(array $data) => $data['store_id'] === null
        ));

        $this->users->method('findAll')->willReturn([['id' => 1], ['id' => 2]]);
        $this->notifs->expects($this->once())->method('notifyMany')->with(
            [1, 2],
            'notebook_entry_created',
            'notif_notebook_entry_created_body',
            [],
            7,
        );

        $response = $this->controller->store($this->request(1, null));

        $this->assertStringContainsString('success=created', $this->redirectLocation($response));
    }

    // ── update() / destroy() : auteur vs notebook.manage ────────────────

    public function testUpdateAllowsTheAuthorToEditTheirOwnNoteWithoutManagePermission(): void
    {
        $this->grantStoreScopedRole(9, 1, ['notebook.create']); // pas de notebook.manage
        $this->notes->method('findById')->willReturn(['id' => 5, 'author_id' => 9, 'store_id' => 1, 'content' => 'old', 'expires_at' => null]);
        $_POST = ['content' => 'new content'];

        $req = $this->request(9, [1]);
        $req->setRouteParams(['id' => '5']);

        $this->notes->expects($this->once())->method('save')->with($this->callback(
            fn(array $data) => $data['content'] === 'new content'
        ));

        $response = $this->controller->update($req);

        $this->assertStringContainsString('success=updated', $this->redirectLocation($response));
    }

    public function testUpdateAllowsAManagerToEditSomeoneElsesNote(): void
    {
        $this->grantStoreScopedRole(2, 1, ['notebook.manage']);
        $this->notes->method('findById')->willReturn(['id' => 5, 'author_id' => 9, 'store_id' => 1, 'content' => 'old', 'expires_at' => null]);
        $_POST = ['content' => 'edited by manager'];

        $req = $this->request(2, [1]);
        $req->setRouteParams(['id' => '5']);

        $this->notes->expects($this->once())->method('save');

        $response = $this->controller->update($req);

        $this->assertStringContainsString('success=updated', $this->redirectLocation($response));
    }

    public function testUpdateForbidsANonAuthorWithoutManagePermission(): void
    {
        $this->grantStoreScopedRole(2, 1, ['notebook.create']); // pas manage
        $this->notes->method('findById')->willReturn(['id' => 5, 'author_id' => 9, 'store_id' => 1, 'content' => 'old', 'expires_at' => null]);
        $_POST = ['content' => 'attempted edit'];

        $this->notes->expects($this->never())->method('save');

        $req = $this->request(2, [1]);
        $req->setRouteParams(['id' => '5']);

        $this->expectException(ForbiddenException::class);
        $this->controller->update($req);
    }

    public function testDestroyForbidsANonAuthorWithoutManagePermission(): void
    {
        $this->grantStoreScopedRole(2, 1, ['notebook.create']);
        $this->notes->method('findById')->willReturn(['id' => 5, 'author_id' => 9, 'store_id' => 1]);

        $this->notes->expects($this->never())->method('delete');

        $req = $this->request(2, [1]);
        $req->setRouteParams(['id' => '5']);

        $this->expectException(ForbiddenException::class);
        $this->controller->destroy($req);
    }

    public function testDestroyAllowsTheAuthorToDeleteTheirOwnNote(): void
    {
        $this->grantStoreScopedRole(9, 1, []);
        $this->notes->method('findById')->willReturn(['id' => 5, 'author_id' => 9, 'store_id' => 1]);
        $this->notes->expects($this->once())->method('delete')->with(5);

        $req = $this->request(9, [1]);
        $req->setRouteParams(['id' => '5']);

        $response = $this->controller->destroy($req);

        $this->assertStringContainsString('success=deleted', $this->redirectLocation($response));
    }

    // ── togglePin() ──────────────────────────────────────────────────────

    public function testTogglePinFlipsPinnedState(): void
    {
        $this->grantGlobalRole(1);
        $this->notes->method('findById')->willReturn(['id' => 5, 'author_id' => 9, 'store_id' => null, 'pinned' => 0]);
        $this->notes->expects($this->once())->method('save')->with($this->callback(
            fn(array $data) => $data['pinned'] === 1
        ));

        $req = $this->request(1, null);
        $req->setRouteParams(['id' => '5']);

        $response = $this->controller->togglePin($req);

        $this->assertStringContainsString('success=updated', $this->redirectLocation($response));
    }

    // ── index() ──────────────────────────────────────────────────────────

    public function testIndexOrdersPinnedFirstAndExcludesExpiredByDefault(): void
    {
        $this->grantGlobalRole(1);
        $viewDir = sys_get_temp_dir() . '/kintai-notebook-view-' . uniqid();
        $this->ensureViewFile($viewDir, 'notebook.php', '<?php ?>');
        $this->ensureViewFile($viewDir . '/layout', 'app.php', '<?php echo json_encode(array_column($entries, "id")); ?>');

        $view = new ViewRenderer($viewDir);
        $view->addNamespace('notebook', $viewDir);
        $controller = new NotebookController(
            $view,
            $this->notes,
            $this->stores,
            $this->storeUsers,
            $this->users,
            new AuditLogger(),
            $this->notifs,
            new PermissionService($this->assignments, $this->roles),
        );

        $this->notes->method('findVisibleForStores')->willReturn([
            ['id' => 1, 'author_id' => 1, 'store_id' => 1, 'content' => 'récente', 'pinned' => 0, 'expires_at' => null, 'created_at' => '2026-09-20 10:00:00'],
            ['id' => 2, 'author_id' => 1, 'store_id' => null, 'content' => 'épinglée', 'pinned' => 1, 'expires_at' => null, 'created_at' => '2026-09-10 08:00:00'],
            ['id' => 3, 'author_id' => 1, 'store_id' => 1, 'content' => 'expirée', 'pinned' => 0, 'expires_at' => '2020-01-01 00:00:00', 'created_at' => '2026-09-21 10:00:00'],
        ]);

        $response = $controller->index($this->request(1, null));
        $ids = json_decode($response->body(), true);

        $this->assertSame([2, 1], $ids);
    }

    public function testIndexIncludesExpiredWhenShowExpiredIsRequested(): void
    {
        $this->grantGlobalRole(1);
        $viewDir = sys_get_temp_dir() . '/kintai-notebook-view-' . uniqid();
        $this->ensureViewFile($viewDir, 'notebook.php', '<?php ?>');
        $this->ensureViewFile($viewDir . '/layout', 'app.php', '<?php echo json_encode(array_column($entries, "id")); ?>');

        $view = new ViewRenderer($viewDir);
        $view->addNamespace('notebook', $viewDir);
        $controller = new NotebookController(
            $view,
            $this->notes,
            $this->stores,
            $this->storeUsers,
            $this->users,
            new AuditLogger(),
            $this->notifs,
            new PermissionService($this->assignments, $this->roles),
        );

        $this->notes->method('findVisibleForStores')->willReturn([
            ['id' => 3, 'author_id' => 1, 'store_id' => 1, 'content' => 'expirée', 'pinned' => 0, 'expires_at' => '2020-01-01 00:00:00', 'created_at' => '2026-09-21 10:00:00'],
        ]);

        $_GET = ['show_expired' => '1'];
        $response = $controller->index($this->request(1, null));
        $_GET = [];
        $ids = json_decode($response->body(), true);

        $this->assertSame([3], $ids);
    }

    private function ensureViewFile(string $dir, string $filename, string $content): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($dir . DIRECTORY_SEPARATOR . $filename, $content);
    }
}
