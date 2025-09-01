<?php

declare(strict_types=1);

namespace App\Controllers;

use Elementary\Http\Request;
use Elementary\Http\Response;
use App\Models\User;
use Elementary\Utils\ParameterBag;
use Elementary\Validation\Validator;

class LoginController extends AbstractController
{

    public function showLoginPage(Request $request): Response
    {
        $errors = new ParameterBag();

        return $this->render('signin.html.php', [
            'request' => $request,
            'errors' => $errors
        ]);
    }

    public function loginUser(Request $request): Response 
    {
        $data = $request->post->all();

        $validator = new Validator($data, [
            'username' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if ($validator->fails()) {
            $this->flashBag->add('errors', $validator->errors());
            return new Response(302, '', ['Location' => '/login']);
        }

        $user = User::findByEmail($data['username']);

        if (!$user || !password_verify($data['password'], $user->password)) {
            $this->flashBag->add('errors', ['credentials' => 'Invalid username or password.']);
            return new Response(302, '', ['Location' => '/login']);
        }

        // Log the user in by setting the session
        $request->session->set('user_id', $user->id);

        // Redirect to the profile page
        return new Response(302, '', ['Location' => '/profile']);
    }

}