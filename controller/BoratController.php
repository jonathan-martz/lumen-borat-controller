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

        $package = DB::table('packages')
            ->where('vendor','=',$routeInfo[2]['vendor'])
            ->where('module','=',$routeInfo[2]['module']);

        $package = $package->first();

        $this->cloneRepo($package);
        $versions = $this->listVersions($package);

        $data = $this->generatePackage($package, $versions);

        return response()->json(
            $data
        );
    }

    public function getComposerData($package, $hash, $exec){
        $composer = shell_exec('cd repo/' . $package->fullname . ' && git show '.$hash.':composer.json'. ' 2>&1');

        return json_decode($composer);
    }

    public function generatePackage($package, array $versions):array{

        foreach($versions as $key => $value){
            $cmd = 'cd repo/' . $package->fullname . ' && git rev-list -n 1 ' . $value. ' 2>&1';
            $exec = shell_exec($cmd);
            $exec = trim($exec, PHP_EOL);

            $packages[$value] = $this->getComposerData($package, $value, $exec);
        }

        return [
            'packages' => [
                $package->fullname => $packages
            ]
        ];
    }

    public function cloneRepo($package){
        if(!file_exists('repo')){
            mkdir('repo');
        }

        if(!file_exists('repo/'.$package->vendor)){
            mkdir('repo/'.$package->vendor);
        }

        $command = 'cd repo/'.$package->vendor . ' && git clone '.$package->repo.' '.$package->module . ' 2>&1';

        $output = shell_exec($command);
    }

    public function listVersions($package):array{
        $command = 'cd repo/' . $package->fullname . ' && git tag -l';
        $output = shell_exec($command);

        $output = trim($output,PHP_EOL);

        $versions = explode(PHP_EOL, $output);
        return $versions;
    }

}
