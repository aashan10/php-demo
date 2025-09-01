<?php

declare(strict_types=1);

namespace App\Controllers;

use Elementary\Http\Request;
use Elementary\Http\Response;
use App\Models\User;
use Elementary\Utils\ParameterBag;
use Elementary\Validation\Validator;

class RegisterController extends AbstractController
{
    public function showRegisterPage(Request $request): Response
    {
        $errors = new ParameterBag();
        return $this->render('register.html.php', [
            'request' => $request,
            'errors' => $errors
        ]);
    }

    public function registerUser(Request $request): Response
    {
        $data = $request->post->all();

        $validator = new Validator($data, [
            'FirstName' => ['required'],
            'LastName' => ['required'],
            'Address' => ['required'],
            'username' => ['required', 'email'],
            'password' => ['required', 'minLength:8'],
        ]);

        if ($validator->fails()) {
            $this->flashBag->add('errors', $validator->errors());
            return new Response(302, '', ['Location' => '/Register']);
        }

        // Check for user uniqueness after basic validation passes
        if (User::findByEmail($data['username'])) {
            $this->flashBag->add('errors', ['username' => 'A user with this email address already exists.']);
            return new Response(302, '', ['Location' => '/Register']);
        }

        // Hash the password for security
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);

        // Create the new user
        User::create([
            'FirstName' => $data['FirstName'],
            'LastName' => $data['LastName'],
            'Address' => $data['Address'],
            'username' => $data['username'], // email
            'password' => $hashedPassword,
        ]);

        // Redirect to the login page
        return new Response(302, '', ['Location' => '/login']);
    }
}