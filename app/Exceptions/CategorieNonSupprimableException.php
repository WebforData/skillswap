<?php

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class CategorieNonSupprimableException extends AdminException
{
    public function __construct(string $message = "Impossible de supprimer cette catégorie car elle contient des offres associées (RG04).")
    {
        parent::__construct($message, Response::HTTP_CONFLICT);
    }
}
