<?php

namespace App\Http\Controllers;

use \http\Env\Response;
use \Illuminate\Http\Request;
use \Illuminate\Support\Facades\DB;
use \Illuminate\Support\Facades\Hash;

class BoratController extends Controller
{
    public function packages(){
        return 'packages';
    }

    public function package(){
        return 'package';
    }
}
