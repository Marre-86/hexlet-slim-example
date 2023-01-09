<?php

// Подключение автозагрузки через composer
require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;

if (PHP_SAPI === 'cli-server' && $_SERVER['SCRIPT_FILENAME'] !== __FILE__) {
    return false;
}

session_start();

$container = new Container();
$container->set('renderer', function () {
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});
$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});

AppFactory::setContainer($container);
$app = AppFactory::create();

$app->addErrorMiddleware(true, true, true);

$router = $app->getRouteCollector()->getRouteParser();

$users = ['mike', 'mishel', 'adel', 'keks', 'kamila'];

$app->get('/', function ($request, $response) use ($router) {
    $params = ['greeting' => 'Welcome to Slim!',
               'router' => $router,  // unfort. didnt find a way to get all routes out and throw them in a loop
               'dynamic_route_example' => $router->urlFor('dynamic_route_example', ['id' => 'Zaluzhny']),
               'get_form_example' => $router->urlFor('get_form_example'),
               'post_form_example' => $router->urlFor('post_form_example')];
    return $this->get('renderer')->render($response, "main.phtml", $params);
});

$app->post('/users2', function ($request, $response) {
    $user = $request->getParsedBodyParam('user');
    $file = __DIR__ . '/../users/users.txt';
    file_put_contents($file, trim(json_encode($user)) . PHP_EOL, FILE_APPEND | LOCK_EX);
    $this->get('flash')->addMessage('success', 'User added!');
    return $response->withRedirect('/users2', 302);
});

$app->get('/users2', function ($request, $response) {
    $file = __DIR__ . '/../users/users.txt';
    $lines = explode(PHP_EOL, trim(file_get_contents($file)));
    $scorers = array();
    foreach ($lines as $line) {
        $scorers[] = json_decode($line, true);
    }
    $flashes = $this->get('flash')->getMessages();
    $params = ['scorers' => $scorers, 'flash' => $flashes];
    return $this->get('renderer')->render($response, 'scorers.phtml', $params);
});

$app->get('/users2/new', function ($request, $response) {
    $params = [
        'user' => ['nickname' => '', 'email' => ''],
        'errors' => []
    ];
    return $this->get('renderer')->render($response, "users/new.phtml", $params);
})->setName('post_form_example');

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
