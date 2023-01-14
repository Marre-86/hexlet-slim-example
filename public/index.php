<?php

namespace App;

// Подключение автозагрузки через composer
require __DIR__ . '/../vendor/autoload.php';

use App\Functions;
use Slim\Factory\AppFactory;
use DI\Container;
use Slim\Middleware\MethodOverrideMiddleware;

if (PHP_SAPI === 'cli-server' && $_SERVER['SCRIPT_FILENAME'] !== __FILE__) {
    return false;
}

session_start();
$_SESSION['accepted_login'] = ['999', '777'];

$container = new Container();
$container->set('renderer', function () {
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});
$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});

AppFactory::setContainer($container);
$app = AppFactory::create();
$app->add(MethodOverrideMiddleware::class);
$app->addErrorMiddleware(true, true, true);

$router = $app->getRouteCollector()->getRouteParser();

$users = ['mike', 'mishel', 'adel', 'keks', 'kamila'];

$app->delete('/', function ($req, $res) {
    $_SESSION = [];
    session_destroy();
    $params = [];
    return $this->get('renderer')->render($res, 'main.phtml', $params);
});

$app->post('/', function ($req, $res) use ($router) {
    $login = $req->getParsedBodyParam('login');
    if (in_array($login['codename'], $_SESSION['accepted_login'])) {
        $username = $login['codename'];
        $_SESSION['username'] = $login['codename'];
        return $res->withRedirect($router->urlFor('index'));
    } else {
        $errors = [];
        $errors['invalid_login'] = 'This login is not accepted. Try again';
    }
    $params = ['greeting' => 'No mistakes anymore, motherfucker!', 'errors' => $errors];
    return $this->get('renderer')->render($res, 'main.phtml', $params);
});

$app->get('/', function ($request, $response) use ($router) {
    $params = ['greeting' => 'Welcome to Slim! Login please',
               'router' => $router,  // unfort. didnt find a way to get all routes out and throw them in a loop
               'dynamic_route_example' => $router->urlFor('dynamic_route_example', ['id' => 'Zaluzhny']),
               'get_form_example' => $router->urlFor('get_form_example'),
               'index' => $router->urlFor('post_form_example')];
    return $this->get('renderer')->render($response, "main.phtml", $params);
});

$app->delete('/users2/{id}', function ($req, $res, $args) use ($router) {                                  // 7
    $cookies = json_decode($req->getCookieParam('cookie', json_encode([])), true);
    $cookiesDeleted = array();
    foreach ($cookies as $cookie) {
        if ($cookie['id'] !== $args['id']) {
            $cookiesDeleted[] = $cookie;
        }
    }
    $encoded = json_encode($cookiesDeleted);
    $this->get('flash')->addMessage('success', 'User has been removed');
    return $res->withHeader('Set-Cookie', "cookie={$encoded}; path=/")->withRedirect($router->urlFor('index'));
});

$app->patch('/users2/{id}', function ($req, $res, $args) use ($router) {                 // 6
    $scorer = $req->getParsedBodyParam('scorer');
    $cookies = json_decode($req->getCookieParam('cookie', json_encode([])), true);
    $cookiesPatched = array();
    foreach ($cookies as $cookie) {
          $cookiesPatched[] = ($cookie['id'] === $args['id']) ? $scorer : $cookie;
    }
    $encoded = json_encode($cookiesPatched);
    $errors = [];
    if (strlen($scorer['nickname']) < 2) {
        $errors['nickname'] = 'This field should be longer than one character';
    }
    if (count($errors) === 0) {
        $this->get('flash')->addMessage('success', 'User has been updated!');
        return $res->withHeader('Set-Cookie', "cookie={$encoded}; path=/")->withRedirect($router->urlFor('index'), 302);
    }
    $params = ['scorer' => $scorer, 'errors' => $errors];
    return $this->get('renderer')->render($res, 'users/edit.phtml', $params);
});

$app->get('/users2/{id}/edit', function ($req, $res, $args) {                            // 5
    $cookies = json_decode($req->getCookieParam('cookie', json_encode([])), true);
    foreach ($cookies as $cookie) {
        if ($cookie['id'] === $args['id']) {
            $scorerFound = $cookie;
        }
    }
    if (!$scorerFound) {
        return $response->withStatus(405);
    }
    $params = ['scorer' => $scorerFound, 'errors' => [] ];
    return $this->get('renderer')->render($res, 'users/edit.phtml', $params);
});

$app->post('/users2', function ($request, $response) use ($router) {                    // 4
    $user = $request->getParsedBodyParam('user');
    $cookie = json_decode($request->getCookieParam('cookie', json_encode([])), true);
    $cookie[] = $user;
    $encodedCookie = json_encode($cookie);
    $errors = [];
    if (strlen($user['nickname']) < 2) {
        $errors['nickname'] = 'This field should be longer than one character';
    }
    if (count($errors) === 0) {
        $this->get('flash')->addMessage('success', 'User added!');
        return $response->withHeader('Set-Cookie', "cookie={$encodedCookie};  path=/")->withRedirect($router->urlFor('index'), 302);
    }
    $params = ['user' => $user, 'errors' => $errors];
    return $this->get('renderer')->render($response, "users/new.phtml", $params);
});

$app->get('/users2', function ($request, $response) use ($router) {                     // 1 index
    $cookies = json_decode($request->getCookieParam('cookie', json_encode([])), true);
    $scorers = array();
    $page = $request->getQueryParam('page', 1);
    foreach ($cookies as $cookie) {
          $scorers[] = $cookie;
    }
    $scorersCurrentPage = array_slice($scorers, $page * 5 - 5, 5);
    if (!$_SESSION['username']) {
        return $response->write('You have not been authorized to this page')->withStatus(403);
    }
    if (empty($scorersCurrentPage) && $cookies) {
        return $response->write('This page does not exist')->withStatus(404);
    }
    $flashes = $this->get('flash')->getMessages();
    $params = ['scorers' => $scorersCurrentPage, 'page' => $page, 'flash' => $flashes,
               'post_form_example' => $router->urlFor('post_form_example'),
               'username' => $SESSION['username']];
    return $this->get('renderer')->render($response, 'scorers.phtml', $params);
})->setName('index');

$app->get('/users2/new', function ($request, $response) {                 // 3
    $params = [
        'user' => ['nickname' => '', 'email' => ''],
        'errors' => []
    ];
    return $this->get('renderer')->render($response, "users/new.phtml", $params);
})->setName('post_form_example');

$app->get('/users2/{id}', function ($request, $response, $args) {        // 2 show
    $cookies = json_decode($request->getCookieParam('cookie', json_encode([])), true);
    foreach ($cookies as $cookie) {
        if ($cookie['id'] === $args['id']) {
            $scorerFound = $cookie;
        }
    }
    if (!$scorerFound) {
        return $response->withStatus(404);
    }
    $params = ['scorer' => $scorerFound];
    return $this->get('renderer')->render($response, 'show.phtml', $params);
});

$app->get('/users/{id}', function ($request, $response, $args) {
    $params = ['id' => $args['id'], 'nickname' => 'user-' . $args['id']];
    // Указанный путь считается относительно базовой директории для шаблонов, заданной на этапе конфигурации
    // $this доступен внутри анонимной функции благодаря https://php.net/manual/ru/closure.bindto.php
    // $this в Slim это контейнер зависимостей
    return $this->get('renderer')->render($response, 'users/show.phtml', $params);
})->setName('dynamic_route_example');

$app->get('/users', function ($request, $response) use ($users) {
    $term = $request->getQueryParam('term');
    $params = ['users' => $users, 'term' => $term];
    return $this->get('renderer')->render($response, 'users/index.phtml', $params);
})->setName('get_form_example');

$app->post('/users', function ($request, $response) {
    return $response->withStatus(302);
});

$app->get('/courses/{id}', function ($request, $response, array $args) {
    $id = $args['id'];
    return $response->write("Course id: {$id}");
});

$app->run();
