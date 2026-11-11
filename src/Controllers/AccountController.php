<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Logger;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\User;
use App\Support\EmployeeManager;
use App\Support\MainWindow;

/**
 * MY ACCOUNT module: every signed-in user can change their own password.
 */
final class AccountController
{
    public function index(): void
    {
        View::display('main', MainWindow::data('account', $_GET));
    }

    /** POST /account/password */
    public function updatePassword(): void
    {
        $user = User::find((int) (Session::get('user')['id'] ?? 0));
        if ($user === null) {
            Session::destroy();
            Response::redirect('/login');
        }

        $result = EmployeeManager::changePassword(
            $user,
            (string) ($_POST['current_password'] ?? ''),
            (string) ($_POST['new_password'] ?? ''),
            (string) ($_POST['confirm_password'] ?? ''),
        );

        if (! $result['ok']) {
            Session::flash($result['message'], 'Password Not Changed', 'warning');
            Response::redirect('/account');
        }

        Session::regenerate(); // new session id + CSRF token after a credential change
        Logger::info("Password changed for '{$user->username}'");
        Session::flash($result['message'], 'Password Changed', 'information');
        Response::redirect($user->role === 'admin' ? '/inventory' : '/payroll');
    }
}
