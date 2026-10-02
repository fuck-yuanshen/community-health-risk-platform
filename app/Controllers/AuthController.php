<?php

declare(strict_types=1);

namespace App\Controllers;

final class AuthController extends BaseController
{
    public function login(): void
    {
        if ($this->auth->check()) {
            redirect('dashboard');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $username = trim((string) post('username'));
            $password = (string) post('password');

            if ($this->auth->attempt($username, $password)) {
                $user = $this->auth->user();
                $this->logger->log($user['id'] ?? null, '用户登录', 'users', $user['id'] ?? null);
                redirect('dashboard');
            }

            flash('用户名或密码错误', 'error');
            redirect('login');
        }

        $this->render('auth/login', ['title' => '登录']);
    }

    public function logout(): void
    {
        $user = $this->auth->user();
        $this->auth->logout();
        $this->logger->log($user['id'] ?? null, '用户登出', 'users', $user['id'] ?? null);
        redirect('login');
    }
}
