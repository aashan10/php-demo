<?php

declare(strict_types=1);

namespace App\Controllers;

use Elementary\Http\Response;

class PagesController extends AbstractController
{
    public function home(): Response
    {
        return $this->render('pages/home');
    }
}
