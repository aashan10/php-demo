<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Utils\ParameterBag;

class GithubController extends AbstractController
{
    public function githubRegisterPage(Request $request): Response
    {
        $errors = new ParameterBag();
        return $this->render('profile.html.php', [
            'request' => $request,
            'errors' => $errors
        ]);
    }

    public function githubUser(Request $request): Response
    {
        $errors = [];

        // Collecting inputs
        $Name        = $request->post->get('Name');
        $PublicEmail = $request->post->get('PublicEmail');
        $Bio         = $request->post->get('Bio');
        $URl         = $request->post->get('URl');
        $Company     = $request->post->get('Company');

        // File must come from $_FILES, not post
        $File = $request->files->get('File');

        // --- Validations ---

        if (strlen((string)$Name) < 2) {
            $errors['Name'] = 'Name is required (at least 2 characters)';
        }

        if (filter_var($PublicEmail, FILTER_VALIDATE_EMAIL) === false) {
            $errors['PublicEmail'] = 'Public Email must be a valid email address';
        }

        if (empty($Bio)) {
            $errors['Bio'] = 'Write something in your bio';
        }

        if (!empty($URl)) {
            if (!str_starts_with($URl, "www") || filter_var("http://" . $URl, FILTER_VALIDATE_URL) === false) {
                $errors['URL'] = 'URL must start with www and be valid';
            }
        } else {
            $errors['URL'] = 'URL is required';
        }

        if (empty($Company)) {
            $errors['Company'] = 'Company is required';
        }

        // --- File Validation ---
        if ($File && $File['error'] === UPLOAD_ERR_OK) {
            $allowedExt = ['jpg', 'jpeg', 'png', 'pdf'];
            $ext = strtolower(pathinfo($File['name'], PATHINFO_EXTENSION));

            if (!in_array($ext, $allowedExt)) {
                $errors['File'] = 'Invalid file type. Only JPG, PNG, and PDF allowed.';
            }

            if ($File['size'] > 2 * 1024 * 1024) { // 2MB max
                $errors['File'] = 'File too large. Max size is 2MB.';
            }
        } else {
            $errors['File'] = 'Please upload a file.';
        }


        // --- Return with errors if any ---
        if (!empty($errors)) {
            $errors = new ParameterBag($errors);
            return $this->render('profile.html.php', [
                'request' => $request,
                'errors' => $errors
            ]);
        }

        move_uploaded_file($File['tmp_name'], UPLOAD_PATH . 'images.png');
        // If no errors → success
        return new Response(200, 'profile.html.php');
    }
}
