<?php

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class EvaluationNonAutoriseeException extends EvaluationException
{
    public function __construct(string $message = "Vous n'êtes pas autorisé à évaluer cette séance (RG15).")
    {
        parent::__construct($message, Response::HTTP_FORBIDDEN);
    }
}
