<?php

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class LitigeDejaResoluException extends LitigeException
{
    public function __construct(string $message = "Ce litige a déjà fait l'objet d'une décision arbitrale définitive (RG14).")
    {
        parent::__construct($message, Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
