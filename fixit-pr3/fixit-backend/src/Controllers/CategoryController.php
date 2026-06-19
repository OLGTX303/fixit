<?php

declare(strict_types=1);

namespace FixIt\Controllers;

use FixIt\Models\CategoryModel;
use FixIt\Support\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class CategoryController
{
    public function list(Request $request, Response $response): Response
    {
        $categories = (new CategoryModel())->all();
        return ResponseHelper::json($response, $categories);
    }
}