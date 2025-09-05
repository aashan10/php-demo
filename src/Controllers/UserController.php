<?php

declare(strict_types=1);

namespace App\Controllers;

use Elementary\Http\Response;

class UserController extends AbstractController
{
    public function index(): Response
    {
        $users = [
            [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'posts' => [
                    ['title' => 'Hello World', 'date' => '2024-01-01'],
                    ['title' => 'PHP Tips', 'date' => '2024-01-02']
                ]
            ],
            [
                'name' => 'Jane Smith',
                'email' => 'jane@example.com',
                'posts' => [
                    ['title' => 'Template Engines', 'date' => '2024-01-03']
                ]
            ]

        ];

        // This render method now comes from the updated AbstractController
        return $this->render('users/index', ['users' => $users]);
    }
}
