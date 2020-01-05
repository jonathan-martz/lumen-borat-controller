<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Class BoratController
 * @package App\Http\Controllers
 */
class BoratController
{
    /**
     * @var bool
     */
    public $cache = true;

    /**
     * @param Request $request
     * @return mixed
     * @throws Exception
     * @todo replace $request with $this->request
     */
    public function packages(Request $request)
    {
        $routeInfo = $request->route();

        if($routeInfo[2]['type'] == 'private' || $routeInfo[2]['type'] == 'public' || $routeInfo[2]['type'] == 'proxy') {
            if(!file_exists('cache')) {
                mkdir('cache');
            }

            $filename = 'cache/' . $routeInfo[2]['type'] . '.json';

            if(file_exists($filename) && $this->cache) {
                $file = file_get_contents($filename);

                return response()->json(
                    json_decode($file)
                );
            }
            else {
                $packages = DB::table('packages')->where('type', '=', $routeInfo[2]['type']);

                if($packages->count() != 0) {
                    $json = [
                        'providers' => [],
                        'mirrors' => [
                            'dist-url' => 'https://' . $_SERVER['SERVER_NAME'] . '/' . $routeInfo[2]['type'] . '/dists/%package%/%version%/%reference%.%type%',
                            'preferred' => true
                        ],
                        'providers-url' => '/' . $routeInfo[2]['type'] . '/p/%package%.json'
                    ];

                    foreach($packages->get() as $key => $value) {
                        $json['providers'][$value->fullname] = [
                            'sha256' => null
                        ];
                    }

                    if($this->cache) {
                        file_put_contents($filename, json_encode($json));
                    }

                    return response()->json(
                        $json
                    );
                }
                else {
                    throw new Exception('No package found.');
                }
            }
        }
        else {
            throw new Exception("Only public and private type available.");
        }
    }

    /**
     * @param Request $request
     * @return mixed
     * @throws Exception
     */
    public function package(Request $request)
    {
        $routeInfo = $request->route();

        if($routeInfo[2]['type'] == 'private' || $routeInfo[2]['type'] == 'public' || $routeInfo[2]['type'] == 'proxy') {
            if(!file_exists('cache')) {
                mkdir('cache');
            }

            $filename = 'cache/' . $routeInfo[2]['vendor'] . '-' . $routeInfo[2]['module'] . '.json';

            if(!file_exists($filename) && $this->cache) {
                $package = DB::table('packages')
                    ->where('fullname', '=', $routeInfo[2]['vendor'] . '/' . $routeInfo[2]['module'])
                    ->where('type', '=', $routeInfo[2]['type']);

                if($package->count() != 0) {
                    $package = $package->first();

                    $this->cloneRepo($package, $routeInfo);
                    $versions = $this->listVersions($package, $routeInfo);

                    $data = $this->generatePackage($package, $versions, $routeInfo);

                    if($this->cache) {
                        file_put_contents($filename, json_encode($data));
                    }

                    return response()->json(
                        $data
                    );
                }
                else {
                    throw new Exception('No package found.');
                }
            }
            else {
                $file = file_get_contents($filename);

                return response()->json(
                    json_decode($file)
                );
            }
        }
        else {
            throw new Exception("Only public and private type available.");
        }
    }

    /**
     * @param $package
     * @param $version
     * @param $hash
     * @param $routeInfo
     */
    public function downloadZip($package, $version, $hash, $routeInfo)
    {
        if(!file_exists($routeInfo[2]['type'])) {
            mkdir($routeInfo[2]['type']);
        }

        if(!file_exists($routeInfo[2]['type'] . '/dists')) {
            mkdir($routeInfo[2]['type'] . '/dists');
        }

        $tmp = explode('/', $package->fullname);

        if(!file_exists($routeInfo[2]['type'] . '/dists/' . $tmp[0])) {
            mkdir($routeInfo[2]['type'] . '/dists/' . $tmp[0]);
        }

        if(!file_exists($routeInfo[2]['type'] . '/dists/' . $package->fullname)) {
            mkdir($routeInfo[2]['type'] . '/dists/' . $package->fullname);
        }

        if(!file_exists($routeInfo[2]['type'] . '/dists/' . $package->fullname . '/' . $this->normalizeVersion($version))) {
            mkdir($routeInfo[2]['type'] . '/dists/' . $package->fullname . '/' . $this->normalizeVersion($version));
        }

        $path = $routeInfo[2]['type'] . '/repo/' . $package->fullname;
        $file = '../../../' . $routeInfo[2]['type'] . '/dists/' . $package->fullname . '/' . $this->normalizeVersion($version) . '/' . $hash;
        $cmd = 'cd ' . $path . ' && git archive --format zip -o ' . $file . '.zip ' . $hash . ' 2>&1';
        $exec = shell_exec($cmd);
    }

    /**
     * @param $package
     * @param $version
     * @param $hash
     * @param $routeInfo
     * @return mixed|string|null
     */
    public function getComposerData($package, $version, $hash, $routeInfo)
    {
        $composer = shell_exec('cd ' . $routeInfo[2]['type'] . '/repo/' . $package->fullname . ' && git show ' . $hash . ':composer.json' . ' 2>&1');

        $this->downloadZip($package, $version, $hash, $routeInfo);

        $composer = json_decode($composer, JSON_FORCE_OBJECT);

        $composer['uid'] = $hash;
        $composer['version_normalized'] = $this->normalizeVersion($version);
        $composer['version'] = $version;
        $composer['dist'] = [
            "type" => "zip",
            "url" => 'https://' . $_SERVER['SERVER_NAME'] . '/' . $routeInfo[2]['type'] . '/dists/' . $package->fullname . '/' . $this->normalizeVersion($version) . '/' . $hash . '.zip',
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

    /**
     * @param $version
     * @return string
     */
    public function normalizeVersion($version)
    {
        // Todo: rewrite function!!!
        $tmp = explode('.', $version);
        if(count($tmp) !== 1) {
            if(count($tmp) === 2) {
                $version .= '.0.0';
            }
            else {
                if(count($tmp) === 3) {
                    $version .= '.0';
                }
            }
        }
        return $version;
    }

    /**
     * @param $package
     * @param array $versions
     * @param $routeInfo
     * @return array
     */
    public function generatePackage($package, array $versions, $routeInfo): array
    {

        foreach($versions as $key => $value) {
            $cmd = 'cd ' . $routeInfo[2]['type'] . '/repo/' . $package->fullname . ' && git rev-list -n 1 ' . $value . ' 2>&1';
            $exec = shell_exec($cmd);
            $exec = trim($exec, PHP_EOL);

            $packages[$value] = $this->getComposerData($package, $value, $exec, $routeInfo);
        }

        return [
            'packages' => [
                $package->fullname => $packages
            ]
        ];
    }

    /**
     * @param $package
     * @param $routeInfo
     * @throws Exception
     */
    public function cloneRepo($package, $routeInfo)
    {
        $tmp = explode('/', $package->fullname);
        if(file_exists($routeInfo[2]['type'] . '/repo/' . $tmp[0])) {
            shell_exec('rm -rf ' . $routeInfo[2]['type'] . '/repo/' . $tmp[0]);
        }

        if(!file_exists($routeInfo[2]['type'])) {
            mkdir($routeInfo[2]['type']);
        }

        if(!file_exists($routeInfo[2]['type'] . '/repo')) {
            mkdir($routeInfo[2]['type'] . '/repo');
        }

        if(!file_exists($routeInfo[2]['type'] . '/repo/' . $tmp[0])) {
            mkdir($routeInfo[2]['type'] . '/repo/' . $tmp[0]);
        }

        $command = 'cd ' . $routeInfo[2]['type'] . '/repo/' . $tmp[0] . ' rm -rf ' . $tmp[1] . ' && git clone ' . $package->repo . ' ' . $tmp[1] . ' 2>&1';

        $output = shell_exec($command);

        if(strpos($output, 'Permission denied') !== false) {
            throw new Exception($output);
        }
    }

    /**
     * @param $package
     * @param $routeInfo
     * @return array
     */
    public function listVersions($package, $routeInfo): array
    {
        $command = 'cd ' . $routeInfo[2]['type'] . '/repo/' . $package->fullname . ' && git tag -l';
        $output = shell_exec($command);

        $output = trim($output, PHP_EOL);

        return explode(PHP_EOL, $output);
    }
}
