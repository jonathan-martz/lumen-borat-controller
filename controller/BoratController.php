<?php

namespace App\Http\Controllers;

use \http\Env\Response;
use \Illuminate\Http\Request;
use \Illuminate\Support\Facades\DB;
use \Illuminate\Support\Facades\Hash;

class BoratController extends Controller
{
    public function packages(Request $request){
        $packages = DB::table('packages');

        $json = [
            'providers' => [],
            'mirrors' => [
                'dist-url' => 'https://api-borat.jmartz.de/dists/%package%/%version%/%reference%.%type%',
                'preferred' => true
            ],
            'providers-url' => '/p/%package%.json'
        ];

        foreach ($packages->get() as $key => $value){
            $json['providers'][$value->fullname] = [
                'sha256' => null
            ];
        }

        return response()->json(
            $json
        );
    }

    public function package(Request $request){
        $routeInfo = $request->route();
        // var_dump($routeInfo[2]['vendor']);
        // var_dump($routeInfo[2]['module']);

        $this->cloneRepo();
        $this->listVersions();

    }

    public function cloneRepo(){

    }

    public function listVersions(){

    }

}
