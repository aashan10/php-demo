<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use Elementary\Http\Request;
use Elementary\Http\Response;
use Elementary\Validation\Validator;
use Elementary\Utils\SessionBag;
use Elementary\Http\Controller;

class AuthController extends Controller
{
    public function showLoginForm(): Response
    {
        return $this->render('auth/login');
    }

    public function login(Request $request, SessionBag $session): Response
    {
        $data = $request->post->only(['email', 'password', 'remember']);
        
        $validator = new Validator($data, [
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if ($validator->fails()) {
            return $this->render('auth/login', [
                'errors' => $validator->errors(),
                'old' => $data,
            ]);
        }

        /** @var User|null $user */
        $user = User::query()->where('email', '=', $data['email'])->first();

        if (!$user || !password_verify($data['password'], $user->password)) {
            $this->addFlash('error', 'Invalid credentials.');
            return $this->render('auth/login', [
                'old' => $data,
            ]);
        }

        $session->set('user_id', $user->id);

        $redirection = $session->get('redirection_url_after_login', null);

        if ($redirection) {
            $session->remove('redirection_url_after_login');
            $response =  $this->redirect($redirection);
        } else {
            $response = $this->redirectToRoute('home');
        }

        if (!empty($data['remember'])) {
            $token = bin2hex(random_bytes(32));
            $hashedToken = hash('sha256', $token);
            $user->update(['remember_token' => $hashedToken]);

            // The cookie should contain the raw token, not the hash
            $cookieValue = "{$user->id}|{$token}";
            $response->cookies->set('elementary_auth', $cookieValue, time() + 60 * 60 * 24 * 30);
        }

        return $response;
    }

    public function showRegistrationForm(): Response
    {
        return $this->render('auth/register');
    }

    public function register(Request $request, SessionBag $session): Response
    {
        $data = $request->post->only(['first_name', 'last_name', 'email', 'password', 'password_confirmation']);

        $validator = new Validator($data, [
            'first_name' => ['required'],
            'last_name' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'minLength:8'],
            'password_confirmation' => ['required', 'equals:password'],
        ]);

        if ($validator->fails()) {
            return $this->render('auth/register', [
                'errors' => $validator->errors(),
                'old' => $data,
            ]);
        }
        
        $existingUser = User::query()->where('email', '=', $data['email'])->first();
        if ($existingUser) {
            return $this->render('auth/register', [
                'errors' => ['email' => 'The email has already been taken.'],
                'old' => $data,
            ]);
        }

        User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'password' => password_hash($data['password'], PASSWORD_DEFAULT),
            'is_active' => 1,
            'profile_picture' => 'default.jpg',
        ]);
        
        $newUser = User::query()->where('email', '=', $data['email'])->first();

        $session->set('user_id', $newUser->id);

        return $this->redirectToRoute('home');
    }

    public function logout(SessionBag $session): Response
    {
        $session->destroy();
        
        $response = $this->redirectToRoute('home');
        $response->cookies->set('elementary_auth', '', time() - 3600);

        return $response;
    }
}
