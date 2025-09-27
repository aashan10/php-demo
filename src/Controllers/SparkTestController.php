<?php

declare(strict_types=1);

namespace App\Controllers;

use Elementary\Http\Controller;
use Elementary\Http\Request;
use Elementary\Http\Response;

class SparkTestController extends Controller
{

    public function index(Request $request): Response 
    {
        return $this->render('test/spark');
    }
}
