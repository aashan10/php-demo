<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use Elementary\Http\Request;
use Elementary\Http\Response;
use Elementary\Utils\FlashBag;
use Elementary\Validation\Validator;
use Elementary\Http\Controller;
use Psr\Log\LoggerInterface;

class UserController extends Controller
{
    public function index(): Response
    {

        $users = User::all();

        // This render method now comes from the updated AbstractController
        return $this->render('users/index', ['users' => $users]);
    }

    public function create(LoggerInterface $logger): Response
    {
        $logger->info('create page accessed!');
        return $this->render('users/create');
    }

    public function store(Request $request, FlashBag $flashBag): Response 
    {
        $data = $request->post->only([
            'first_name',
            'last_name',
            'email',
            'password',
            'password_confirmation'
        ]);

        $validator = new Validator($data, [
            'first_name' => ['required'],
            'last_name' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'minLength:8'],
            'password_confirmation' => ['required', 'equals:password'],
        ]);


        if ($validator->fails()) {
            $errors = $validator->errors();

            return $this->render('users/create', [
                'errors' => $errors,
                'old' => $data,
            ]);
        }

        User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'password' => password_hash($data['password'], PASSWORD_BCRYPT),
            'is_active' => 1,
            'profile_picture' => 'default.jpg',
        ]);
        
        $flashBag->add('success', 'User created successfully!');

        return $this->redirectToRoute('users.index');
    }
}
