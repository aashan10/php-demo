<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use Elementary\Http\Response;

class UserController extends AbstractController
{
    public function index(): Response
    {

        /* $users = [ */
        /*     [ */
        /*         'first_name' => 'John', */
        /*         'last_name' => 'Doe', */
        /*         'email' => 'test@example.com', */
        /*         'is_active' => 1, */
        /*         'profile_picture' => 'john.jpg', */
        /*         'password' => password_hash('securepassword', PASSWORD_BCRYPT), */
        /*     ], */
        /*     [ */
        /*         'first_name' => 'Jane', */
        /*         'last_name' => 'Smith', */
        /*         'email' => 'test2@example.com', */
        /*         'is_active' => 0, */
        /*         'profile_picture' => 'jane.jpg', */
        /*         'password' => password_hash('anotherpassword', PASSWORD_BCRYPT), */
        /*     ], */
        /*     [ */
        /*         'first_name' => 'Bob', */
        /*         'last_name' => 'Brown', */
        /*         'email' => 'test3@example.com', */
        /*         'is_active' => 1, */
        /*         'profile_picture' => 'bob.jpg', */
        /*         'password' => password_hash('yetanotherpassword', PASSWORD_BCRYPT), */
        /*     ], */
        /**/
        /* ]; */

        /* foreach ($users as $user) { */
        /*     // User::create($user); */
        /* } */

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
