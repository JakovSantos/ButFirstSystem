<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Logger;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;

/**
 * The C# "Login" form (Form1).
 */
final class AuthController
{
    public function show(): void
    {
        if (Session::get('user') !== null) {
            Response::redirect('/inventory');
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

        // Case-insensitive username, case-sensitive password (as in C#).
        $users = (array) App::config('users', []);
        $key = strtolower($username);
        $account = $users[$key] ?? null;

        if ($account === null || ! password_verify($password, (string) $account['password_hash'])) {
            Logger::info("Failed login for '{$username}'");
            Session::flashInput(['username' => $username]);
            Session::flash('Invalid username or password.', 'Login Failed', 'error');
            Response::redirect('/login');
        }

        Session::regenerate();
        Session::put('user', ['username' => $key, 'role' => $account['role']]);
        Logger::info("Login '{$key}' ({$account['role']})");

        Response::redirect('/inventory');
    }

    public function logout(): void
    {
        Session::destroy();
        Response::redirect('/login');
    }
}
