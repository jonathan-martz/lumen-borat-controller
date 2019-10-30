<?php

namespace App\Http\Controllers;

use \http\Env\Response;
use \Illuminate\Http\Request;
use \Illuminate\Support\Facades\DB;
use \Illuminate\Support\Facades\Hash;


class BoratController
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

        if(!file_exists('cache')){
            mkdir('cache');
        }

        $filename = 'cache/'.$routeInfo[2]['vendor'].'-'.$routeInfo[2]['module'].'.json';

        if(!file_exists($filename)){
            $package = DB::table('packages')
                ->where('vendor','=',$routeInfo[2]['vendor'])
                ->where('module','=',$routeInfo[2]['module']);

            $package = $package->first();

            $this->cloneRepo($package);
            $versions = $this->listVersions($package);

            $data = $this->generatePackage($package, $versions);

            file_put_contents($filename, json_encode($data));

            return response()->json(
                $data
            );
        }
        else{
            $file = file_get_contents($filename);

            return response()->json(
                json_decode($file)
            );
        }
    }

    public function downloadZip($package, $version, $hash){
        if(!file_exists('dists')){
            mkdir('dists');
        }

        if(!file_exists('dists/' .$package->vendor)){
            mkdir('dists/'.$package->vendor);
        }

        if(!file_exists('dists/' . $package->fullname)){
            mkdir('dists/'. $package->fullname);
        }

        if(!file_exists('dists/' .$package->fullname.'/'.$this->normalizeVersion($version))){
            mkdir('dists/' .$package->fullname.'/'.$this->normalizeVersion($version));
        }

        $path = 'repo/' . $package->fullname;
        $file = '../../../dists/' . $package->fullname . '/' . $this->normalizeVersion($version) . '/' . $hash;
        $cmd = 'cd ' . $path . ' && git archive --format zip -o ' . $file . '.zip ' . $hash.' 2>&1';
        $exec = shell_exec($cmd);
    }

    public function getComposerData($package, $version, $hash){
        $composer = shell_exec('cd repo/' . $package->fullname . ' && git show '.$hash.':composer.json'. ' 2>&1');

        $this->downloadZip($package, $version, $hash);

        $composer = json_decode($composer, JSON_FORCE_OBJECT);

        $composer['uid'] = $hash;
        $composer['version_normalized'] = $this->normalizeVersion($version);
        $composer['version'] = $version;
        $composer['dist'] = [
            "type" => "zip",
            "url" => 'https://borat.jmartz.de/private/dists/' . $package->fullname . '/' . $this->normalizeVersion($version) . '/' . $hash . '.zip',
            "reference" => $hash,
            "shasum" => ""
        ];

        $composer['source'] = [
            "type" => "git",
            "url" => $package->repo,
            "reference" => $hash
        ];

        $composer['time'] = time();

        $composer['support'] = [
            'source' => ''
        ];

        return $composer;
    }

    public function normalizeVersion($version){
        // Todo: rewrite function!!!
        $tmp = explode('.', $version);
        if(count($tmp) !== 1){
            if(count($tmp) === 2){
                $version .= '.0.0';
            }
            else{
                if(count($tmp) === 3){
                    $version .= '.0';
                }
            }
        }
        return $version;
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
        if(file_exists('repo/'.$package->vendor)){
            shell_exec('rm -rf repo/'.$package->vendor);
        }

        if(!file_exists('repo')){
            mkdir('repo');
        }

        if(!file_exists('repo/'.$package->vendor)){
            mkdir('repo/'.$package->vendor);
        }

        $command = 'cd repo/'.$package->vendor . ' rm -rf '.$package->module . ' && git clone '.$package->repo.' '.$package->module . ' 2>&1';

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
