<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/Env.php';

use Slim\Exception\HttpNotFoundException;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Factory\AppFactory;
use DI\Container;
use Utils\Helper;
use Middleware\CorsMiddleware;

$container = new Container();
AppFactory::setContainer($container);
$app = AppFactory::create();
 
(require __DIR__ . '/../config/Settings.php')($container);
(require __DIR__ . '/../config/Dependencies.php')($container);

$app->addBodyParsingMiddleware();
$app->add(CorsMiddleware::class);

// Error middleware setup - always show errors for debugging
$displayErrors = true; // Force display errors for debugging
$errorMiddleware = $app->addErrorMiddleware($displayErrors, true, true);
$customNotFoundHandler = function () use ($app) {
  $response = $app->getResponseFactory()->createResponse();
  return $response->withStatus(404);
};
$errorMiddleware->setErrorHandler(HttpNotFoundException::class, $customNotFoundHandler);
 
(require __DIR__ . '/../src/Controllers/v1/AuthController.php')($app);
(require __DIR__ . '/../src/Controllers/v1/UserController.php')($app);
(require __DIR__ . '/../src/Controllers/v1/UserActivityLogController.php')($app);
(require __DIR__ . '/../src/Controllers/v1/NotificationController.php')($app);
(require __DIR__ . '/../src/Controllers/v1/CategoryController.php')($app);
(require __DIR__ . '/../src/Controllers/v1/ProgramQuestionController.php')($app);
(require __DIR__ . '/../src/Controllers/v1/VideoController.php')($app);
(require __DIR__ . '/../src/Controllers/v1/BookController.php')($app);
(require __DIR__ . '/../src/Controllers/v1/CourseController.php')($app);
(require __DIR__ . '/../src/Controllers/v1/LessonController.php')($app);
(require __DIR__ . '/../src/Controllers/v1/QuizController.php')($app);
(require __DIR__ . '/../src/Controllers/v1/UserCourseProgressController.php')($app);
// (require __DIR__ . '/../src/Controllers/v1/MediaController.php')($app); // Optional secure media streaming
(require __DIR__ . '/../src/Controllers/v1/TestController.php')($app);
 
$app->any('/ver', function (Request $request, Response $response) {
  $payload = json_encode([
    'version' => 'v1.0.5'
  ]);
  $response->getBody()->write($payload);
  return $response->withHeader('Content-Type', 'application/json');
});

try {
  $app->run();
} catch (Exception $e) {
  return Helper::errorResponse("Something went wrong!", 500);
}
