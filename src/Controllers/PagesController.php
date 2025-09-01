<?php

declare(strict_types=1);

namespace App\Controllers;

use Elementary\Http\Request;
use Elementary\Http\Response;
use App\Models\User;
use App\Repositories\UserRepositoryInterface;
use Elementary\Utils\ParameterBag;
use Elementary\Utils\UploadedFile;
use Elementary\Utils\FlashBag;

class PagesController extends AbstractController {

    private UserRepositoryInterface $userRepository;

    // Modified constructor for dependency injection
    public function __construct(UserRepositoryInterface $userRepository, FlashBag $flashBag)
    {
        parent::__construct($flashBag); // Call parent constructor
        $this->userRepository = $userRepository;
    }

    public function homePage(Request $request): Response 
    {
        return $this->render('amrit.html.php', [
            'name' => 'Amrit'
        ]);
    }

    public function profilePage(Request $request): Response 
    {
        if (!$request->session->has('user_id')) {
            return new Response(302, '', ['Location' => '/login']);
        }

        // Use the injected repository
        $user = $this->userRepository->find($request->session->get('user_id'));

        if (!$user) {
            $request->session->destroy();
            return new Response(302, '', ['Location' => '/login']);
        }

        return $this->render('profile.html.php', [
            'user' => $user,
            'errors' => new ParameterBag()
        ]);
    }

    public function updateProfile(Request $request): Response 
    {
        if (!$request->session->has('user_id')) {
            return new Response(302, '', ['Location' => '/login']);
        }

        // Use the injected repository
        $user = $this->userRepository->find($request->session->get('user_id'));
        if (!$user) {
            $request->session->destroy();
            return new Response(302, '', ['Location' => '/login']);
        }

        $errors = [];

        /** @var UploadedFile|null $image */
        $file = $request->files->get('profile_picture', null);

        if ($file === null || $file->error !== UPLOAD_ERR_OK) {
            $errors['profile_picture'] = 'Profile picture is required!';
        } else {
            // A more secure way to generate a filename
            $fileName = uniqid('user_' . $user->id . '_') . '.' . $file->getExtension();

            if ($file->move(PUBLIC_PATH . '/uploads/' . $fileName) === false) {
                $errors['profile_picture'] = 'Failed to upload profile picture!';
            }
        }


        return $this->render('profile.html.php', [
            'user' => $user,
            'errors' => new ParameterBag($errors),
        ]);
    }

    public function showUserProfile(Request $request): Response
    {
        $id = (int) $request->attributes->get('id');
        // Use the injected repository
        $user = $this->userRepository->find($id);

        if (!$user) {
            return new Response(404, 'User not found');
        }

        return $this->render('user_profile.html.php', [
            'user' => $user
        ]);
    }
}
