<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Logger;
use App\Models\User;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;

/**
 * Sign-in and sign-out.
 */
final class AuthController
{
    public function show(): void
    {
        $user = Session::get('user');
        if ($user !== null) {
            Response::redirect(($user['role'] ?? '') === 'admin' ? '/inventory' : '/payroll');
        }

        View::display('login', [
            'title'    => 'But First System',
            'username' => (string) (Session::pullInput()['username'] ?? ''),
        ], 'layout');
    }

    public function login(): void
    {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = trim((string) ($_POST['password'] ?? ''));

        if ($username === '' || $password === '') {
            Session::flashInput(['username' => $username]);
            Session::flash('Please enter both username and password.', 'Login Error', 'warning');
            Response::redirect('/login');
        }

        // Case-insensitive username (admin, or an Employee ID such as F-001), case-sensitive password.
        $account = User::findByUsername($username);

        if ($account === null || ! password_verify($password, (string) $account->password_hash)) {
            Logger::info("Failed login for '{$username}'");
            Session::flashInput(['username' => $username]);
            Session::flash('Invalid username or password.', 'Login Failed', 'error');
            Response::redirect('/login');
        }

        $name = $account->role === 'employee' ? (string) ($account->payroll?->name ?? $account->username) : 'Admin';

        Session::regenerate();
        Session::put('user', [
            'id'          => $account->id,
            'username'    => $account->username,
            'role'        => $account->role,
            'employee_id' => $account->employee_id,
            'name'        => $name,
        ]);
        Logger::info("Login '{$account->username}' ({$account->role})");

        // A new or reset account sets its own password first.
        if ($account->must_change_password) {
            Session::flash(
                "Welcome, {$name}! Please set your own password to finish signing in.",
                'Set Your Password',
                'information'
            );
            Response::redirect('/account');
        }

        // Employees land on their own payroll; admins on the inventory.
        Response::redirect($account->role === 'admin' ? '/inventory' : '/payroll');
    }

    public function logout(): void
    {
        Session::destroy();
        Response::redirect('/login');
    }
}
