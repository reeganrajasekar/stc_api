<?php

use Slim\App;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Services\v1\BookService;
use Utils\Helper;
use Middleware\JwtMiddleware;
use Middleware\RoleMiddleware;

return function (App $app) {
    $container = $app->getContainer();

    // Get all books with pagination and filters
    $app->get('/v1/books', function (Request $request, Response $response) use ($container) {
        try {
            $bookService = new BookService($container->get(PDO::class));
            $queryParams = $request->getQueryParams();
            
            $page = isset($queryParams['page']) ? (int)$queryParams['page'] : 1;
            $limit = isset($queryParams['limit']) ? (int)$queryParams['limit'] : 10;
            $categoryId = isset($queryParams['category_id']) ? (int)$queryParams['category_id'] : null;
            $isPopular = isset($queryParams['is_popular']) ? (int)$queryParams['is_popular'] : null;
            $isRecommended = isset($queryParams['is_recommended']) ? (int)$queryParams['is_recommended'] : null;
            
            // Validate pagination parameters
            if ($page < 1) $page = 1;
            if ($limit < 1 || $limit > 100) $limit = 10;
            
            $result = $bookService->getAllBooks($page, $limit, $categoryId, $isPopular, $isRecommended);
            
            return Helper::jsonResponse([
                'success' => true,
                'message' => 'Books retrieved successfully',
                'data' => $result
            ], 200);
            
        } catch (\Exception $e) {
            return Helper::errorResponse($e->getMessage(), 500);
        }
    })->add(new RoleMiddleware(['user']))->add(new JwtMiddleware());

    // Get books by category with pagination
    $app->get('/v1/books/category/{category_id}', function (Request $request, Response $response, array $args) use ($container) {
        try {
            $bookService = new BookService($container->get(PDO::class));
            $categoryId = (int)$args['category_id'];
            $queryParams = $request->getQueryParams();
            
            $page = isset($queryParams['page']) ? (int)$queryParams['page'] : 1;
            $limit = isset($queryParams['limit']) ? (int)$queryParams['limit'] : 10;
            
            if ($categoryId <= 0) {
                return Helper::errorResponse('Invalid category ID', 400);
            }
            
            // Validate pagination parameters
            if ($page < 1) $page = 1;
            if ($limit < 1 || $limit > 100) $limit = 10;
            
            $result = $bookService->getBooksByCategory($categoryId, $page, $limit);
            
            return Helper::jsonResponse([
                'success' => true,
                'message' => 'Books retrieved successfully',
                'data' => $result
            ], 200);
            
        } catch (\Exception $e) {
            return Helper::errorResponse($e->getMessage(), 500);
        }
    })->add(new RoleMiddleware(['user']))->add(new JwtMiddleware());

    // Get popular books
    $app->get('/v1/books/popular', function (Request $request, Response $response) use ($container) {
        try {
            $bookService = new BookService($container->get(PDO::class));
            $queryParams = $request->getQueryParams();
            
            $page = isset($queryParams['page']) ? (int)$queryParams['page'] : 1;
            $limit = isset($queryParams['limit']) ? (int)$queryParams['limit'] : 10;
            
            // Validate pagination parameters
            if ($page < 1) $page = 1;
            if ($limit < 1 || $limit > 100) $limit = 10;
            
            $result = $bookService->getPopularBooks($page, $limit);
            
            return Helper::jsonResponse([
                'success' => true,
                'message' => 'Popular books retrieved successfully',
                'data' => $result
            ], 200);
            
        } catch (\Exception $e) {
            return Helper::errorResponse($e->getMessage(), 500);
        }
    })->add(new RoleMiddleware(['user']))->add(new JwtMiddleware());

    // Get recommended books
    $app->get('/v1/books/recommended', function (Request $request, Response $response) use ($container) {
        try {
            $bookService = new BookService($container->get(PDO::class));
            $queryParams = $request->getQueryParams();
            
            $page = isset($queryParams['page']) ? (int)$queryParams['page'] : 1;
            $limit = isset($queryParams['limit']) ? (int)$queryParams['limit'] : 10;
            
            // Validate pagination parameters
            if ($page < 1) $page = 1;
            if ($limit < 1 || $limit > 100) $limit = 10;
            
            $result = $bookService->getRecommendedBooks($page, $limit);
            
            return Helper::jsonResponse([
                'success' => true,
                'message' => 'Recommended books retrieved successfully',
                'data' => $result
            ], 200);
            
        } catch (\Exception $e) {
            return Helper::errorResponse($e->getMessage(), 500);
        }
    })->add(new RoleMiddleware(['user']))->add(new JwtMiddleware());

    // Search books
    $app->get('/v1/books/search', function (Request $request, Response $response) use ($container) {
        try {
            $bookService = new BookService($container->get(PDO::class));
            $queryParams = $request->getQueryParams();
            
            $query = isset($queryParams['q']) ? trim($queryParams['q']) : '';
            $page = isset($queryParams['page']) ? (int)$queryParams['page'] : 1;
            $limit = isset($queryParams['limit']) ? (int)$queryParams['limit'] : 10;
            
            if (empty($query)) {
                return Helper::errorResponse('Search query is required', 400);
            }
            
            if (strlen($query) < 2) {
                return Helper::errorResponse('Search query must be at least 2 characters long', 400);
            }
            
            // Validate pagination parameters
            if ($page < 1) $page = 1;
            if ($limit < 1 || $limit > 100) $limit = 10;
            
            $result = $bookService->searchBooks($query, $page, $limit);
            
            return Helper::jsonResponse([
                'success' => true,
                'message' => 'Books search completed successfully',
                'data' => $result
            ], 200);
            
        } catch (\Exception $e) {
            return Helper::errorResponse($e->getMessage(), 500);
        }
    })->add(new RoleMiddleware(['user']))->add(new JwtMiddleware());

    // Get specific book by ID
    $app->get('/v1/books/{book_id}', function (Request $request, Response $response, array $args) use ($container) {
        try {
            $bookService = new BookService($container->get(PDO::class));
            $bookId = (int)$args['book_id'];
            
            if ($bookId <= 0) {
                return Helper::errorResponse('Invalid book ID', 400);
            }
            
            $book = $bookService->getBookById($bookId);
            
            return Helper::jsonResponse([
                'success' => true,
                'message' => 'Book retrieved successfully',
                'data' => $book
            ], 200);
            
        } catch (\Exception $e) {
            $statusCode = $e->getMessage() === 'Book not found' ? 404 : 500;
            return Helper::errorResponse($e->getMessage(), $statusCode);
        }
    })->add(new RoleMiddleware(['user']))->add(new JwtMiddleware());

    // Get all book categories
    $app->get('/v1/book-categories', function (Request $request, Response $response) use ($container) {
        try {
            $bookService = new BookService($container->get(PDO::class));
            $categories = $bookService->getAllBookCategories();
            
            return Helper::jsonResponse([
                'success' => true,
                'message' => 'Book categories retrieved successfully',
                'data' => $categories
            ], 200);
            
        } catch (\Exception $e) {
            return Helper::errorResponse($e->getMessage(), 500);
        }
    })->add(new RoleMiddleware(['user']))->add(new JwtMiddleware());

    // Get specific book category by ID
    $app->get('/v1/book-categories/{category_id}', function (Request $request, Response $response, array $args) use ($container) {
        try {
            $bookService = new BookService($container->get(PDO::class));
            $categoryId = (int)$args['category_id'];
            
            if ($categoryId <= 0) {
                return Helper::errorResponse('Invalid category ID', 400);
            }
            
            $category = $bookService->getBookCategoryById($categoryId);
            
            return Helper::jsonResponse([
                'success' => true,
                'message' => 'Book category retrieved successfully',
                'data' => $category
            ], 200);
            
        } catch (\Exception $e) {
            $statusCode = $e->getMessage() === 'Book category not found' ? 404 : 500;
            return Helper::errorResponse($e->getMessage(), $statusCode);
        }
    })->add(new RoleMiddleware(['user']))->add(new JwtMiddleware());
};