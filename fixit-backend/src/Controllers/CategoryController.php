<?php

declare(strict_types=1);

namespace FixIt\Controllers;

use FixIt\Models\CategoryModel;
use FixIt\Support\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;

final class CategoryController
{
    public function list(Response $response): Response
    {
        $categories = (new CategoryModel())->all();
        return ResponseHelper::json($response, $categories);
    }
}