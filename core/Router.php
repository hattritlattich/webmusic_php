<?php
class Router {
    private $routes = [
        'home' => 'HomeController@index',
        'login' => 'AuthController@login',
        'register' => 'AuthController@register',
        'logout' => 'AuthController@logout',
        'admin' => 'AdminController@index',
        'admin/songs' => 'AdminController@songs',
        'admin/artists' => 'AdminController@artists',
        'admin/playlists' => 'AdminController@playlists',
        'admin/users' => 'AdminController@users'
    ];

    public function run() {
        echo "Router is running!<br>";

        $url = isset($_GET['url']) ? rtrim($_GET['url'], '/') : 'home';
        echo "URL: $url <br>";

        $url = explode('/', $url);
        $controllerName = ucfirst($url[0]) . 'Controller';
        $controllerFile = __DIR__ . "/../app/controllers/$controllerName.php";

        echo "Looking for controller file: $controllerFile <br>";

        if (file_exists($controllerFile)) {
            require_once $controllerFile;

            if (class_exists($controllerName)) {
                $controller = new $controllerName();
                $method = isset($url[1]) ? $url[1] : 'index';

                if (method_exists($controller, $method)) {
                    echo "Calling method: $method <br>";
                    $controller->$method();
                } else {
                    http_response_code(404);
                    echo "404 - Method Not Found ($method)";
                }
            } else {
                http_response_code(500);
                echo "500 - Controller Class Not Found";
            }
        } else {
            http_response_code(404);
            echo "404 - Controller Not Found ($controllerName)";
        }
    }
}
?>
