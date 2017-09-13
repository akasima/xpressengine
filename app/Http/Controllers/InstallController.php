<?php
/**
 * @author      XE Developers <developers@xpressengine.com>
 * @copyright   2015 Copyright (C) NAVER Corp. <http://www.navercorp.com>
 * @license     http://www.gnu.org/licenses/old-licenses/lgpl-2.1.html LGPL-2.1
 * @link        https://xpressengine.io
 */

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Artisan;
use File;
use Symfony\Component\Yaml\Yaml;
use Illuminate\Contracts\Cookie\QueueingFactory as JarContract;

class InstallController extends Controller
{
    public function index()
    {
        return view('install.index');
    }

    public function check(Request $request)
    {
        $result = false;
        $message = null;

        switch ($request->get('key')) {
            case "directoryPermission" :
                $paths = [
                    base_path('bootstrap/cache'),
                    base_path('config/production'),
                    base_path('storage/'),
                ];

                foreach ($paths as $path) {
                    if (!is_writable($path)) {
                        $result = false;
                        $message = 'Write permission required to "'.$path.'" directory ';
                        break;
                    }
                }
                break;

            case "phpVersion" :
                $phpVersion = 70000;
                $result = PHP_VERSION_ID < $phpVersion ? false : true;
                break;

            case 'pdo' :
                $result = extension_loaded('PDO') && extension_loaded('pdo_mysql');
                break;

            case "curl" :
                $result = extension_loaded('curl');
                break;

            case "fileinfo" :
                $result = extension_loaded('fileinfo');
                break;

            case "gd" :
                $result = extension_loaded('gd');
                break;

            case "mbstring" :
                $result = extension_loaded('mbstring');
                break;

            case "openssl" :
                $result = extension_loaded('openssl');
                break;

            case "zip" :
                $result = extension_loaded('zip');
                break;
        }

        return response()->json([
            'result' => $result,
            'message' => $message,
        ]);
    }

    public function install(Request $request, JarContract $cookie)
    {
        if ($this->isInstalled() === true) {
            throw new \Exception('Already installed');
        }

        $url = $this->getUrl($request->get('web_url', ''));
        $validator = $this->getValidationFactory()->make(
            array_merge($request->all(), ['web_url' => $url]),
            [
                'admin_email' => 'required|email',
                'admin_password' => 'required|confirmed',
                'admin_password_confirmation' => 'required',
                'database_name' => 'required',
                'database_password' => 'required',
                'web_url' => 'url',
            ]
        );

        if ($validator->fails()) {
            return $this->back($validator->getMessageBag()->first());
        }

        $configPath = storage_path('app') . '/installConfig';

        $string = Yaml::dump([
            'site' => [
                'locale' => $request->get('locale') != '' ? $request->get('locale') : 'ko',
                'url' => $url !== '' ? $url : 'http://localhost',
                'timezone' =>  $request->get('web_timezone') != '' ? $request->get('web_timezone') : 'Asia/Seoul',
            ],
            'admin' => [
                'email' => $request->get('admin_email'),
                'password' => $request->get('admin_password'),
                'display_name' => $request->get('admin_display_name') != '' ? $request->get('admin_display_name') : 'admin',
            ],
            'database' => [
                'host' => $request->get('database_host') != '' ? $request->get('database_host') : 'localhost',
                'port' => $request->get('database_port') != '' ? $request->get('database_port') : '3306',
                'dbname' => $request->get('database_name'),
                'username' => $request->get('database_user_name') != '' ? $request->get('database_user_name') : 'root',
                'password' => $request->get('database_password'),
                'prefix' => $request->get('database_prefix') != '' ? $request->get('database_prefix') : 'xe',
            ],
        ]);

        File::put($configPath, $string);

        Artisan::call('xe:install', [
            '--config' => $configPath,
            '--no-interaction' => true,
        ]);

        File::delete($configPath);

        if (!empty($request->get('locale'))) {
            $cookie->queue($cookie->forever('locale', $request->get('locale')));
        }

        return redirect($request->root());
    }

    /**
     * Is installed
     *
     * @return bool
     */
    protected function isInstalled()
    {
        return file_exists(storage_path() . '/app/installed');
    }

    /**
     * 설치시 세션 아이디가 고정되지 않아 input 이나 error 를 넘겨줄수 없어
     * 스크립트로 메시지를 표시하고 페이지를 되돌림
     *
     * @param string|null $msg
     * @return string
     */
    private function back($msg = null)
    {
        $alert = $msg ? 'alert("'.$msg.'");' : '';
        return sprintf('<script>%s history.back();</script>', $alert);
    }

    /**
     * 입력한 app 의 url 값에 프로토콜이 명시되지 않은 경우 http 로 지정
     *
     * @param string $url
     * @return string
     */
    private function getUrl($url)
    {
        $url = trim($url, '/');
        if (!empty($url) && !preg_match('/^(http(s)?\:\/\/)/', $url)) {
            $url = 'http://' . $url;
        }

        return $url;
    }
}
