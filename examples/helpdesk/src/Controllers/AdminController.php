<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\Requests\UpdateUserRoleRequest;
use App\Models\User;
use Spartan\Attributes\RequireRole;

/**
 * #[RequireRole] on the class guards every action: guests are sent to
 * /login, logged-in users without the role get the 403 page.
 */
#[RequireRole('admin')]
class AdminController extends AppController
{
    public function users(): string
    {
        return $this->render('admin/users', [
            'users' => (new User())->listWithRoles(),
            'roles' => User::ROLES,
        ]);
    }

    public function updateRole(string $id, UpdateUserRoleRequest $request): void
    {
        $userId = self::isId($id) ? (int) $id : 0;
        $target = $userId > 0 ? (new User())->find($userId) : null;

        if ($target === null) {
            $this->session->setFlash('error', trans('app.errors.not_found'));
        } elseif ($userId === (int) $this->user()->id) {
            // Demoting yourself could leave the app without an administrator.
            $this->session->setFlash('error', trans('admin.cannot_change_self'));
        } else {
            (new User())->setRole($userId, (string) $request->post('role'));
            $this->session->setFlash('success', trans('admin.role_updated', ['name' => $target['name']]));
        }
        $this->redirect('/admin/users');
    }
}
