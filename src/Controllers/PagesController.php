<?php

declare(strict_types=1);

namespace App\Controllers;

use Elementary\Http\Response;
use Elementary\Http\Controller;

class PagesController extends Controller
{
    public function home(): Response
    {
        return $this->render('pages/home');
    }
}
